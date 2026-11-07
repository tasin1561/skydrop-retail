<?php

namespace App\Services\Commerce\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * bKash Tokenized Checkout (v1.2.0-beta).
 *   grant token -> create payment (redirect to bkashURL) -> customer returns to callbackURL?paymentID=&status=
 *   -> execute payment server-side (or query status if execute already happened) -> Completed + trxID.
 * Needs BKASH_APP_KEY, BKASH_APP_SECRET, BKASH_USERNAME, BKASH_PASSWORD (sandbox creds from bKash PGW team).
 */
class BkashGateway implements Gateway
{
    public function name(): string
    {
        return 'bkash';
    }

    private function base(): string
    {
        $c = config('retail.payments.bkash');

        return rtrim(config('retail.payments.mode') === 'live' ? $c['live_url'] : $c['sandbox_url'], '/');
    }

    private function token(): string
    {
        $c = config('retail.payments.bkash');
        if (empty($c['app_key']) || empty($c['app_secret'])) {
            throw new RuntimeException('bKash credentials are not configured (BKASH_APP_KEY / BKASH_APP_SECRET).');
        }

        // id_token lives 1 hour; cache for 50 minutes.
        return Cache::remember('bkash:id_token', 3000, function () use ($c) {
            $res = Http::timeout(20)->acceptJson()->withHeaders(['username' => $c['username'], 'password' => $c['password']])
                ->post($this->base().'/tokenized/checkout/token/grant', ['app_key' => $c['app_key'], 'app_secret' => $c['app_secret']]);
            $token = $res->json('id_token');
            if (! $res->successful() || ! $token) {
                throw new RuntimeException('bKash grant token failed: '.$res->json('statusMessage', $res->body()));
            }

            return $token;
        });
    }

    private function http(): PendingRequest
    {
        return Http::timeout(30)->acceptJson()->withHeaders([
            'Authorization' => $this->token(), 'X-APP-Key' => config('retail.payments.bkash.app_key'),
        ]);
    }

    public function initiate(Payment $payment, Order $order, string $callbackUrl): array
    {
        $res = $this->http()->post($this->base().'/tokenized/checkout/create', [
            'mode' => '0011', // checkout (URL based)
            'payerReference' => ltrim((string) ($order->address['phone'] ?? $order->id), '+'),
            'callbackURL' => $callbackUrl,
            'amount' => number_format($payment->amount_bdt, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $order->id.'-'.$payment->id,
        ]);
        $paymentId = $res->json('paymentID');
        if (! $res->successful() || ! $paymentId || $res->json('statusCode') !== '0000') {
            throw new RuntimeException('bKash create payment failed: '.$res->json('statusMessage', $res->body()));
        }
        $payment->gateway_ref = $paymentId;
        $payment->remember(['create' => $res->json()]);
        $payment->save();

        return ['status' => 'redirect', 'redirectUrl' => $res->json('bkashURL'), 'ref' => $paymentId];
    }

    public function confirm(Payment $payment, Request $request): GatewayResult
    {
        $paymentId = (string) $request->input('paymentID');
        if ($paymentId === '' || $paymentId !== $payment->gateway_ref) {
            throw new InvalidCallback('paymentID does not match this payment');
        }
        if ($request->input('status') !== 'success') {
            return GatewayResult::failed('bKash payment '.$request->input('status', 'failed').'.', ['callback' => $request->query()]);
        }
        $res = $this->http()->post($this->base().'/tokenized/checkout/execute', ['paymentID' => $paymentId]);
        $body = $res->json() ?? [];
        if (($body['transactionStatus'] ?? null) !== 'Completed') {
            // Execute may time out or have run already: the status API is the source of truth.
            return $this->query($payment);
        }

        return $this->checkAmount($payment, GatewayResult::paid($body['trxID'] ?? null, ['execute' => $body], (float) ($body['amount'] ?? 0)));
    }

    public function paymentIdFrom(Request $request): ?int
    {
        return null;
    }

    public function query(Payment $payment): GatewayResult
    {
        $res = $this->http()->post($this->base().'/tokenized/checkout/payment/status', ['paymentID' => $payment->gateway_ref]);
        $body = $res->json() ?? [];

        return match ($body['transactionStatus'] ?? null) {
            'Completed' => $this->checkAmount($payment, GatewayResult::paid($body['trxID'] ?? null, ['query' => $body], (float) ($body['amount'] ?? 0))),
            'Initiated', 'Inprogress', 'Pending Authorized' => GatewayResult::pending(['query' => $body]),
            default => GatewayResult::failed('bKash: '.($body['statusMessage'] ?? 'payment not completed'), ['query' => $body]),
        };
    }

    public function refund(Payment $payment, float $amount, string $reason): GatewayResult
    {
        $trx = $payment->payload['trxID'] ?? $payment->payload['execute']['trxID'] ?? $payment->payload['query']['trxID'] ?? null;
        $res = $this->http()->post($this->base().'/tokenized/checkout/payment/refund', [
            'paymentID' => $payment->gateway_ref, 'trxID' => $trx, 'amount' => number_format($amount, 2, '.', ''),
            'sku' => $payment->order_id, 'reason' => mb_substr($reason, 0, 255),
        ]);
        $body = $res->json() ?? [];
        if (($body['transactionStatus'] ?? null) === 'Completed') {
            return new GatewayResult('refunded', $body['refundTrxID'] ?? null, ['refund' => $body], null, $amount);
        }

        return GatewayResult::failed('bKash refund failed: '.($body['statusMessage'] ?? $res->body()), ['refund' => $body]);
    }

    private function checkAmount(Payment $payment, GatewayResult $r): GatewayResult
    {
        if ($r->amount !== null && $r->amount > 0 && abs($r->amount - $payment->amount_bdt) > 0.01) {
            return GatewayResult::failed('Paid amount does not match the order.', $r->payload);
        }
        $r->payload['trxID'] = $r->ref;

        return $r;
    }
}
