<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\User;
use App\Support\Limits;

/**
 * Order limits from App\Support\Limits (Admin → System → Limits), checked by CheckoutService (quote) and
 * OrderService (place). Every failure is a 422 with a shopper-readable message, `code` and `errors.{field}`.
 *
 *  - cart: max distinct items, max qty per item                          (quote + order)
 *  - value: max order total, 50%-advance cap (needs full prepayment)    (quote + order)
 *  - customer: min order total, orders per day, unpaid orders            (order only: a small cart still gets a quote)
 */
class OrderLimits
{
    /** @param  array<int, array<string, mixed>>  $items  request items or cart lines (productId|product_id, qty) */
    public function assertCart(array $items): void
    {
        $maxItems = Limits::int('orders.max_items');
        $distinct = count(array_unique(array_map(fn ($i) => (string) ($i['productId'] ?? $i['product_id'] ?? ''), $items)));
        if ($maxItems > 0 && $distinct > $maxItems) {
            $this->fail('items', 'limit_items', "You can order up to {$maxItems} different items at once. Remove some items or split your order.",
                ['limit' => $maxItems]);
        }
        $maxQty = Limits::int('orders.max_qty');
        foreach (array_values($items) as $i => $item) {
            if ($maxQty > 0 && (int) ($item['qty'] ?? 0) > $maxQty) {
                $this->fail("items.$i.qty", 'limit_qty', "You can order at most {$maxQty} of one item.", ['limit' => $maxQty,
                    'productId' => $item['productId'] ?? $item['product_id'] ?? null]);
            }
        }
    }

    /** @param  array<string, mixed>  $quote  CheckoutService::quote() result */
    public function assertValue(array $quote, ?string $payment = null): void
    {
        $total = (int) ($quote['total'] ?? 0);
        $max = Limits::int('orders.max_value_bdt');
        if ($max > 0 && $total > $max) {
            $this->fail('items', 'limit_order_value', 'Orders above ৳'.number_format($max).' cannot be placed online. Split the order or contact support.',
                ['limit' => $max, 'total' => $total]);
        }
        $partial = Limits::int('orders.max_partial_pay_bdt');
        if ($partial > 0 && in_array($payment, ['advance50', 'half'], true) && $total > $partial) {
            $this->fail('payment', 'full_prepayment_required', 'Orders above ৳'.number_format($partial).' need full payment. Please pay with bKash, Nagad or card.',
                ['limit' => $partial, 'total' => $total]);
        }
    }

    /** Minimum order total, at order creation. */
    public function assertMinimum(array $quote): void
    {
        $total = (int) ($quote['total'] ?? 0);
        $min = Limits::int('orders.min_value_bdt');
        if ($min > 0 && $total < $min) {
            $this->fail('items', 'limit_min_order', 'The minimum order is ৳'.number_format($min).'. Add ৳'.number_format($min - $total).' more to place it.',
                ['limit' => $min, 'total' => $total]);
        }
    }

    /** Per-customer checks at order creation (before the live price check, so a rejected order costs no provider call). */
    public function assertCustomer(User $user): void
    {
        $perDay = Limits::int('orders.max_per_day');
        if ($perDay > 0) {
            $today = Order::query()->where('user_id', $user->id)->where('kind', 'goods')->where('created_at', '>=', now()->startOfDay())->count();
            if ($today >= $perDay) {
                $this->fail('items', 'limit_orders_per_day', "You've placed {$today} orders today, the daily maximum. Please try again tomorrow.",
                    ['limit' => $perDay]);
            }
        }
        $unpaid = Limits::int('orders.max_unpaid');
        if ($unpaid > 0) {
            $open = Order::query()->where('user_id', $user->id)->where('kind', 'goods')->where('status', '!=', 'cancelled')
                ->whereIn('payment_status', ['pending', 'failed'])->count();
            if ($open >= $unpaid) {
                $this->fail('payment', 'limit_unpaid_orders', "You have {$open} unpaid orders. Pay for or cancel one of them before placing a new order.",
                    ['limit' => $unpaid]);
            }
        }
    }

    private function fail(string $field, string $code, string $message, array $extra = []): never
    {
        throw new CommerceException($message, 422, ['errors' => [$field => [$message]]] + $extra, $code);
    }
}
