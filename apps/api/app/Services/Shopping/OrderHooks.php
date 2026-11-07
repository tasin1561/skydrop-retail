<?php

namespace App\Services\Shopping;

use App\Models\Order;

/**
 * Called by OrderService::place inside the order transaction (before payment starts):
 * records the curated-bundle saving and applies the group-buy shipping discount.
 */
class OrderHooks
{
    /** @param array<string, mixed> $quote CheckoutService::quote() result, $data the place() payload */
    public function placed(Order $order, array $quote, array $data): void
    {
        $changes = [];
        if (! empty($quote['bundleDiscount'])) {
            $changes['bundle_discount_bdt'] = (int) $quote['bundleDiscount'];
        }
        // Internal only: GroupBuyService passes ['id' => group id, 'shareBdt' => this member's share of the consolidated shipping].
        $group = $data['group'] ?? null;
        if (is_array($group) && isset($group['id'])) {
            $changes['group_id'] = (int) $group['id'];
            if (isset($group['shareBdt'])) {
                $solo = max(0, (int) $quote['shipping'] - (int) ($quote['shipDiscount'] ?? 0));
                $saving = (int) max(0, $solo - (int) $group['shareBdt']);
                if ($saving > 0) {
                    $changes['group_discount_bdt'] = $saving;
                    $changes['discount_bdt'] = (float) $order->discount_bdt + $saving;
                    $changes['total_bdt'] = max(0, (float) $order->total_bdt - $saving);
                }
            }
        }
        if ($changes) {
            $order->forceFill($changes)->save();
        }
    }

    /** Extra fields for Order::toApi(). */
    public static function api(Order $o): array
    {
        $out = [];
        if ((float) $o->bundle_discount_bdt > 0) {
            $out['bundleDiscount'] = (int) round($o->bundle_discount_bdt);
        }
        if ($o->group_id) {
            $out['groupId'] = (string) $o->group_id;
            $out['groupCode'] = \App\Models\GroupBuy::query()->whereKey($o->group_id)->value('code');
            $out['groupDiscount'] = (int) round($o->group_discount_bdt);
        }
        if ($o->is_preorder) {
            $out['isPreorder'] = true;
            $out['preorder'] = [
                'state' => $o->preorder_state, 'depositPct' => (float) $o->deposit_pct, 'depositBdt' => (int) round($o->deposit_bdt),
                'balanceBdt' => (int) round($o->balanceBdt()), 'eta' => $o->preorder_eta ? substr((string) $o->preorder_eta, 0, 10) : null,
            ];
        }

        return $out;
    }
}
