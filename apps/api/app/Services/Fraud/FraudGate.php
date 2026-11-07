<?php

namespace App\Services\Fraud;

use App\Models\Order;
use App\Support\Features;
use App\Support\Knobs;
use Throwable;

/** Hooks used by checkout/payments: hold high-risk orders and require full prepayment. */
class FraudGate
{
    public static function active(): bool
    {
        try {
            return ! class_exists(Features::class) || Features::enabled('fraud_check');
        } catch (Throwable) {
            return true;
        }
    }

    /** Order must not be verified automatically after payment. */
    public static function holds(Order $order): bool
    {
        return self::active() && (bool) $order->fraud_review;
    }

    /** A 50% advance order that must be paid in full up front (high risk, setting on). */
    public static function requiresFullPrepay(Order $order): bool
    {
        if (! self::active() || ! Knobs::bool('finance.fraud.require_full_prepay_high', true)) {
            return false;
        }
        if (! $order->fraud_checked_at) {
            // The queued check has not run yet: score now from internal signals and cached courier data (no HTTP).
            rescue(fn () => app(FraudService::class)->scoreOrder($order, false), null, true);
        }

        return $order->fraud_level === 'high';
    }
}
