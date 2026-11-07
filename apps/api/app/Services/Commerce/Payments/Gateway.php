<?php

namespace App\Services\Commerce\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface Gateway
{
    public function name(): string;

    /**
     * Starts a payment. $callbackUrl is our webhook URL for this payment (already carries ?payment={id}).
     *
     * @return array{status: 'redirect'|'pending'|'paid', redirectUrl?: string, ref?: string}
     */
    public function initiate(Payment $payment, Order $order, string $callbackUrl): array;

    /** Validates a browser callback or IPN server-side (never trusts the posted status). */
    public function confirm(Payment $payment, Request $request): GatewayResult;

    /** Our payment id from a callback when it is not in ?payment= (e.g. IPN tran_id). */
    public function paymentIdFrom(Request $request): ?int;

    public function query(Payment $payment): GatewayResult;

    public function refund(Payment $payment, float $amount, string $reason): GatewayResult;
}
