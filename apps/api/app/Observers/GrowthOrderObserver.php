<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Growth\AffiliateService;
use App\Services\Growth\ReferralService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Hooks the growth module into order status changes without touching OrderWorkflow:
 * delivered -> start the commission hold + referral points; cancelled/refunded -> reverse the commission.
 * Failures are reported, never thrown, so a status change can not be blocked by a reward.
 * Gift card and Skydrop Plus purchases earn no commission and no referral reward (goods orders only).
 */
class GrowthOrderObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(Order $order): void
    {
        if (! $order->isGoods()) {
            return;
        }
        if ($order->wasChanged('status')) {
            if ($order->status === 'delivered') {
                rescue(fn () => app(AffiliateService::class)->onDelivered($order), null, true);
                rescue(fn () => app(ReferralService::class)->onDelivered($order), null, true);
            }
            if ($order->status === 'cancelled') {
                rescue(fn () => app(AffiliateService::class)->reverseForOrder($order, 'Order cancelled'), null, true);
            }
        }
        if ($order->wasChanged('payment_status') && in_array($order->payment_status, ['refunded', 'refund_pending'], true)) {
            rescue(fn () => app(AffiliateService::class)->reverseForOrder($order, 'Order refunded'), null, true);
        }
    }
}
