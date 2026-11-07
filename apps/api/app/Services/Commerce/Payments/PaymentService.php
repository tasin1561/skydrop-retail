<?php

namespace App\Services\Commerce\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\OrderWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public const METHODS = ['bkash', 'nagad', 'card', 'advance50'];

    public const WALLETS = ['bkash', 'nagad', 'card'];

    public function __construct(private Notifier $notifier) {}

    /** Gateway driver for a wallet id (bkash | nagad | card) or a stored gateway name. */
    public function gateway(string $name): Gateway
    {
        if (config('retail.payments.mode', 'mock') === 'mock' || $name === 'mock') {
            return new MockGateway($name);
        }

        return match ($name) {
            'bkash' => app(BkashGateway::class),
            'nagad' => app(NagadGateway::class),
            'card', 'sslcommerz' => app(SslCommerzGateway::class),
            default => throw new \InvalidArgumentException("Unknown payment gateway {$name}"),
        };
    }

    /** Amount to collect online now. */
    public function amountNow(Order $order): float
    {
        $due = $order->balanceBdt();
        if ($order->payment_method === 'advance50' && (float) $order->paid_bdt <= 0) {
            return (float) ceil($order->total_bdt * config('retail.checkout.advance_ratio', 0.5));
        }

        return $due;
    }

    /** @return array{status: 'pending'|'redirect'|'paid', redirectUrl?: string, amount?: int, paymentId?: int, message?: string} */
    public function start(Order $order): array
    {
        $amount = $this->amountNow($order);
        if ($amount <= 0) {
            if ($order->payment_status !== 'paid') {
                $order->forceFill(['payment_status' => 'paid'])->save();
                app(OrderWorkflow::class)->afterPayment($order);
            }

            return ['status' => 'paid', 'amount' => 0];
        }
        $wallet = $order->payment_method === 'advance50' ? ($order->payment_via ?: 'bkash') : $order->payment_method;
        $gateway = $this->gateway($wallet);
        $payment = Payment::query()->create([
            'order_id' => $order->id, 'gateway' => $gateway->name(), 'amount_bdt' => $amount, 'status' => 'pending',
            'kind' => $order->payment_method === 'advance50' ? 'advance' : 'full', 'payload' => ['wallet' => $wallet],
        ]);

        try {
            $r = $gateway->initiate($payment, $order, $this->callbackUrl($payment));
        } catch (\Throwable $e) {
            Log::error('[payments] initiate failed: '.$e->getMessage(), ['order' => $order->id]);
            $payment->forceFill(['status' => 'failed'])->remember(['error' => $e->getMessage()]);
            $payment->save();

            return ['status' => 'pending', 'amount' => (int) $amount, 'paymentId' => $payment->id,
                'message' => 'We could not reach the payment gateway. Your order is saved; try paying again from your orders page.'];
        }

        return array_filter(['status' => $r['status'], 'redirectUrl' => $r['redirectUrl'] ?? null, 'amount' => (int) $amount, 'paymentId' => $payment->id], fn ($v) => $v !== null);
    }

    public function callbackUrl(Payment $payment): string
    {
        return url('/api/v1/webhooks/payments/'.$payment->gateway).'?payment='.$payment->id;
    }

    /** Finds the payment a callback is about. */
    public function locate(string $gatewayName, Request $request): ?Payment
    {
        $id = $request->integer('payment') ?: $this->gateway($gatewayName)->paymentIdFrom($request);

        return $id ? Payment::query()->where('gateway', $gatewayName)->find($id) : null;
    }

    /**
     * Handles a gateway callback/IPN. Idempotent: a payment that is already final is returned unchanged.
     */
    public function handleCallback(Payment $payment, Request $request): Payment
    {
        if (in_array($payment->status, ['paid', 'refunded'], true)) {
            return $payment;
        }
        $result = $this->gateway($payment->gateway)->confirm($payment, $request);

        return $this->apply($payment, $result);
    }

    public function apply(Payment $payment, GatewayResult $result): Payment
    {
        $applied = false;
        DB::transaction(function () use ($payment, $result, &$applied) {
            $p = Payment::query()->lockForUpdate()->find($payment->id);
            if (in_array($p->status, ['paid', 'refunded'], true)) {
                return;
            }
            $p->remember($result->payload);
            if ($result->status === 'paid') {
                $p->status = 'paid';
                $p->paid_at = now();
                $p->gateway_ref = $p->gateway_ref ?: $result->ref;
                $p->remember(['trxID' => $result->ref]);
                $p->save();

                $order = Order::query()->lockForUpdate()->find($p->order_id);
                $order->paid_bdt = round($order->paid_bdt + $p->amount_bdt, 2);
                $order->payment_status = $order->balanceBdt() > 0 ? 'partial' : 'paid';
                $order->save();
                $applied = true;
            } elseif ($result->status === 'failed') {
                $p->status = 'failed';
                $p->remember(['error' => $result->message]);
                $p->save();
                Order::query()->whereKey($p->order_id)->where('payment_status', 'pending')->update(['payment_status' => 'failed']);
            } else {
                $p->save();
            }
        });

        $payment->refresh();
        if ($applied) {
            $order = $payment->order()->first();
            $this->notifier->paymentReceived($order, (float) $payment->amount_bdt);
            app(OrderWorkflow::class)->afterPayment($order);
        }

        return $payment;
    }

    /** Records cash collected by the Skydrop rider (COD balance of advance50 orders). */
    public function recordCollection(Order $order, float $amount, string $ref): void
    {
        if ($amount <= 0 || Payment::query()->where('order_id', $order->id)->where('kind', 'balance')->where('gateway_ref', $ref)->exists()) {
            return;
        }
        Payment::query()->create(['order_id' => $order->id, 'gateway' => 'skydrop_cod', 'kind' => 'balance', 'amount_bdt' => $amount,
            'status' => 'paid', 'gateway_ref' => $ref, 'paid_at' => now()]);
        $order->paid_bdt = round($order->paid_bdt + $amount, 2);
        $order->payment_status = $order->balanceBdt() > 0 ? 'partial' : 'paid';
        $order->save();
    }

    /** Refunds every captured online payment of an order. Returns the resulting order payment status. */
    public function refundAll(Order $order, string $reason): string
    {
        $pending = false;
        $any = false;
        foreach ($order->payments()->where('status', 'paid')->where('gateway', '!=', 'skydrop_cod')->get() as $p) {
            $any = true;
            $amount = (float) $p->amount_bdt - (float) $p->refunded_bdt;
            try {
                $r = $this->gateway($p->gateway)->refund($p, $amount, $reason);
            } catch (\Throwable $e) {
                $r = GatewayResult::pending(['error' => $e->getMessage()], $e->getMessage());
            }
            $p->remember(['refund' => $r->payload + ['message' => $r->message]]);
            if ($r->status === 'refunded') {
                $p->forceFill(['status' => 'refunded', 'refunded_bdt' => $p->amount_bdt, 'refund_ref' => $r->ref])->save();
                $order->paid_bdt = max(0, round($order->paid_bdt - $amount, 2));
            } else {
                $pending = true;
                $p->forceFill(['status' => 'refund_pending'])->save();
            }
        }
        // Nothing captured online: keep the current status (pending/failed).
        $status = ! $any ? $order->payment_status : ($pending ? 'refund_pending' : 'refunded');
        $order->payment_status = $status;
        $order->save();

        return $status;
    }
}
