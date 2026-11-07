<?php

namespace App\Services\Commerce\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * PAYMENTS_MODE=mock: redirects to a local page (/api/v1/payments/mock/{payment}, signed URL) where you choose
 * success or failure. Callbacks are HMAC-signed with APP_KEY so they cannot be forged from outside.
 */
class MockGateway implements Gateway
{
    public function __construct(private string $method = 'mock') {}

    public function name(): string
    {
        return 'mock';
    }

    public function initiate(Payment $payment, Order $order, string $callbackUrl): array
    {
        $payment->remember(['method' => $this->method]);
        $payment->gateway_ref = 'MOCK-'.Str::upper(Str::random(10));
        $payment->save();

        return ['status' => 'redirect', 'redirectUrl' => URL::temporarySignedRoute('payments.mock.show', now()->addHour(), ['payment' => $payment->id]), 'ref' => $payment->gateway_ref];
    }

    public static function signature(int $paymentId, string $status): string
    {
        return hash_hmac('sha256', $paymentId.'|'.$status, (string) config('app.key'));
    }

    public function confirm(Payment $payment, Request $request): GatewayResult
    {
        $status = (string) $request->input('status');
        if (! hash_equals(self::signature($payment->id, $status), (string) $request->input('signature'))) {
            throw new InvalidCallback('Bad mock signature');
        }

        return $status === 'success'
            ? GatewayResult::paid($payment->gateway_ref ?: 'MOCK-'.$payment->id, ['mock' => true], (float) $payment->amount_bdt)
            : GatewayResult::failed('Payment was not completed (mock).', ['mock' => true]);
    }

    public function paymentIdFrom(Request $request): ?int
    {
        return $request->integer('paymentId') ?: null;
    }

    public function query(Payment $payment): GatewayResult
    {
        return new GatewayResult($payment->status, $payment->gateway_ref);
    }

    public function refund(Payment $payment, float $amount, string $reason): GatewayResult
    {
        return new GatewayResult('refunded', 'MOCKREF-'.Str::upper(Str::random(8)), ['reason' => $reason], null, $amount);
    }
}
