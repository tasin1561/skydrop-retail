<?php

namespace App\Services\Money;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Payments\GatewayResult;
use App\Support\Features;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Wallet as a payment method (a `payments` row with gateway "wallet") and refunds into the wallet.
 * Refund destination: "wallet" (instant, optional bonus %) or "original" (the gateway). The default comes from the
 * setting money.wallet.refund_default; customers (cancel) and staff (refund / cancel) can choose per refund.
 */
class WalletPayments
{
    public const GATEWAY = 'wallet';

    /** @var array<string, string> order id => wallet|original (chosen for the refund that is about to run) */
    private static array $prefs = [];

    public function __construct(private WalletService $wallet) {}

    public static function prefer(string $orderId, ?string $destination): void
    {
        if (in_array($destination, ['wallet', 'original'], true)) {
            self::$prefs[$orderId] = $destination;
        }
    }

    public static function defaultDestination(): string
    {
        return Features::enabled('wallet') && MoneySettings::get('wallet.refund_default', 'original') === 'wallet' ? 'wallet' : 'original';
    }

    public static function bonusPct(): float
    {
        return max(0, min(50, MoneySettings::float('wallet.refund_bonus_pct')));
    }

    /** Pays part or all of a new order from the wallet (inside the order transaction). */
    public function payFromWallet(User $user, Order $order, int $amount): ?Payment
    {
        if ($amount <= 0) {
            return null;
        }
        if (! Features::enabled('wallet')) {
            throw CommerceException::field('useWallet', 'The Skydrop Wallet is not available right now.');
        }
        $amount = (int) min($amount, ceil($order->balanceBdt()));
        $this->wallet->debit($user, $amount, 'order_payment', "Paid on {$order->id}", $order->id);
        $p = Payment::query()->create([
            'order_id' => $order->id, 'gateway' => self::GATEWAY, 'kind' => 'wallet', 'amount_bdt' => $amount, 'status' => 'paid',
            'gateway_ref' => 'WALLET-'.$order->id, 'paid_at' => now(), 'payload' => ['wallet' => 'wallet'],
        ]);
        // Status stays "pending" until the rest is paid (PaymentService::start marks fully covered orders paid).
        $order->forceFill(['paid_bdt' => round((float) $order->paid_bdt + $amount, 2), 'wallet_bdt' => $amount])->save();

        return $p;
    }

    /** Should this payment be refunded into the wallet? */
    public function refundsToWallet(Order $order, Payment $p, ?string $destination = null): bool
    {
        if ($p->gateway === self::GATEWAY) {
            return true; // store credit always goes back to the wallet
        }
        if (! Features::enabled('wallet') || ! $order->user_id) {
            return false;
        }
        $d = $destination ?? (self::$prefs[$order->id] ?? self::defaultDestination());

        return $d === 'wallet';
    }

    /** Credits a refund to the wallet (no payment/order bookkeeping: the caller records it). */
    public function creditRefund(Payment $p, float $amount, string $reason): GatewayResult
    {
        $order = $p->order()->first();
        $user = $order?->user;
        if (! $user) {
            return GatewayResult::failed('No customer to refund to.');
        }
        $bonus = $p->gateway === self::GATEWAY ? 0.0 : round($amount * self::bonusPct() / 100, 2);
        $row = $this->wallet->credit($user, $amount + $bonus, 'refund', 'Refund for '.$order->id.($bonus > 0 ? ' (incl. ৳'.number_format($bonus).' bonus)' : ''),
            $order->id, null, ['payment_id' => $p->id, 'reason' => $reason, 'bonus' => $bonus]);

        return new GatewayResult('refunded', 'WALLET-'.$row->id, ['wallet_ledger_id' => $row->id, 'bonus' => $bonus, 'to' => 'wallet'], null, $amount);
    }

    /** Staff refund of (part of) one payment into the wallet; records it like App\Filament\Support\Ops::refund. */
    public function refundPaymentToWallet(Payment $payment, float $amount, string $reason): string
    {
        $amount = round(min($amount, $payment->amount_bdt - (float) $payment->refunded_bdt), 2);
        if ($amount <= 0) {
            throw new RuntimeException('Nothing left to refund on this payment.');
        }

        return DB::transaction(function () use ($payment, $amount, $reason) {
            $r = $this->creditRefund($payment, $amount, $reason);
            if ($r->status !== 'refunded') {
                throw new RuntimeException((string) $r->message);
            }
            $refunded = (float) $payment->refunded_bdt + $amount;
            $payment->remember(['refunds' => [...($payment->payload['refunds'] ?? []), ['amount' => $amount, 'reason' => $reason, 'ref' => $r->ref, 'to' => 'wallet', 'at' => now()->toIso8601String()]]]);
            $payment->forceFill(['refunded_bdt' => $refunded, 'refund_ref' => $r->ref,
                'status' => $refunded >= $payment->amount_bdt ? 'refunded' : $payment->status])->save();
            $order = $payment->order;
            if ($order) {
                $paid = max(0, (float) $order->paid_bdt - $amount);
                $order->forceFill(['paid_bdt' => $paid, 'payment_status' => $paid <= 0 ? 'refunded' : 'partial'])->save();
            }

            return (string) $r->ref;
        });
    }
}
