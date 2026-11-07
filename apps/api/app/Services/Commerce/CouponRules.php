<?php

namespace App\Services\Commerce;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Support\BizTime;

/**
 * Who may use a coupon and when (all set in Admin → Sales → Coupons). Every failure is `422 errors.coupon`
 * with a shopper-friendly message. The quote pre-checks; OrderService re-checks under a row lock when it claims.
 */
class CouponRules
{
    public const STORE_NAMES = ['amazon' => 'Amazon (amazon.in)', 'flipkart' => 'Flipkart (flipkart.com)', 'bigbasket' => 'BigBasket (bigbasket.com)'];

    /** Active, in its schedule, uses left. */
    public function assertAvailable(Coupon $c): void
    {
        if (! $c->active) {
            throw CommerceException::field('coupon', 'This coupon is no longer available.');
        }
        if (! $c->started()) {
            throw CommerceException::field('coupon', 'This coupon starts on '.$c->starts_at->copy()->timezone(BizTime::TZ)->format('j M, g:i A').' (Bangladesh time).');
        }
        if ($c->expired()) {
            throw CommerceException::field('coupon', 'This coupon has expired.');
        }
        if ($c->max_uses !== null && $c->used >= $c->max_uses) {
            throw CommerceException::field('coupon', 'This coupon has been fully used.');
        }
    }

    /**
     * Allowed phones, first order only and the per-customer limit.
     * A guest may preview a coupon that only has a per-customer limit (it is enforced when the order is placed);
     * coupons limited to certain phones or to a first order need a signed-in shopper.
     */
    public function assertCustomer(Coupon $c, ?User $user, ?string $exceptOrderId = null): void
    {
        if (! $user) {
            if ($c->phoneList() !== [] || $c->first_order_only) {
                throw CommerceException::field('coupon', 'Sign in to use this coupon.');
            }

            return;
        }
        $phones = $c->phoneList();
        if ($phones !== [] && ! in_array(Phone::normalize($user->phone), $phones, true)) {
            throw CommerceException::field('coupon', 'This coupon is not available for your account.');
        }
        $orders = fn () => Order::query()->where('user_id', $user->id)->where('status', '!=', 'cancelled')
            ->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId));
        if ($c->first_order_only && $orders()->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', 'goods'))->exists()) {
            throw CommerceException::field('coupon', 'This coupon is only for your first order.');
        }
        if ($c->per_customer_limit !== null && $orders()->where('coupon_id', $c->id)->count() >= $c->per_customer_limit) {
            throw CommerceException::field('coupon', $c->per_customer_limit === 1
                ? 'You have already used this coupon.'
                : "You have already used this coupon {$c->per_customer_limit} times (the limit per customer).");
        }
    }

    /**
     * Inside the order transaction: lock the coupon row, re-check the customer rules (so two parallel orders from one
     * shopper cannot both pass the per-customer / first-order check) and take one use atomically.
     */
    public function claimFor(Coupon $c, User $user, Order $order): void
    {
        $locked = Coupon::query()->whereKey($c->id)->lockForUpdate()->first();
        if (! $locked) {
            throw CommerceException::field('coupon', 'This coupon code is not valid.');
        }
        $this->assertCustomer($locked, $user, $order->id);
        if (! Coupon::claim($locked->id)) {
            // Another order took the last use between the check and now (atomic, so max_uses is never exceeded).
            throw CommerceException::field('coupon', 'This coupon has just run out.');
        }
    }

    public static function storeName(string $market): string
    {
        return self::STORE_NAMES[$market] ?? ucfirst($market);
    }
}
