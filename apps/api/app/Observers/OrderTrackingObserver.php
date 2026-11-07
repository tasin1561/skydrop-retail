<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Tracking\TrackingService;
use App\Services\Tracking\TrackingSettings;
use Illuminate\Support\Facades\DB;

/**
 * Fires the server-side Purchase at the moment chosen in settings (tracking.purchase_on):
 *   placed    → order created
 *   paid      → payment_status becomes paid or partial (advance paid)
 *   delivered → status becomes delivered (Skydrop webhook / admin, via OrderWorkflow::transition)
 * plus an optional custom "Delivered" event. Runs after the surrounding DB transaction commits.
 *
 * Order kinds: gift card purchases send nothing (a gift card is a liability until redeemed, not a sale);
 * Skydrop Plus purchases send Purchase (real revenue) but never "Delivered".
 */
class OrderTrackingObserver
{
    public function created(Order $order): void
    {
        if (self::untracked($order)) {
            return;
        }
        if (TrackingSettings::purchaseOn() === 'placed') {
            $this->later($order->id, 'Purchase');
        }
    }

    public function updated(Order $order): void
    {
        if (self::untracked($order)) {
            return;
        }
        if ($order->wasChanged('payment_status') && in_array($order->payment_status, ['paid', 'partial'], true)
            && TrackingSettings::purchaseOn() === 'paid') {
            $this->later($order->id, 'Purchase');
        }
        if ($order->wasChanged('status') && $order->status === 'delivered') {
            if (TrackingSettings::purchaseOn() === 'delivered') {
                $this->later($order->id, 'Purchase');
            }
            if (TrackingSettings::sendDelivered() && $order->isGoods()) {
                $this->later($order->id, 'Delivered');
            }
        }
    }

    /** Gift card purchases are never sent to ad platforms. */
    public static function untracked(Order $order): bool
    {
        return ($order->kind ?? 'goods') === 'gift_card';
    }

    private function later(string $orderId, string $event): void
    {
        // Items are written after the order row, inside the same transaction: wait for the commit.
        DB::afterCommit(fn () => app(TrackingService::class)->orderEvent($orderId, $event));
    }
}
