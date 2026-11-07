<?php

namespace App\Models;

use App\Services\Commerce\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-controlled coupon (Sales → Coupons). Eligibility rules live in App\Services\Commerce\CouponRules.
 * expires_at is the end of the schedule ("Ends at" in the admin), starts_at the start; both stored in UTC.
 */
class Coupon extends Model
{
    public const STORES = ['all', 'amazon', 'flipkart', 'bigbasket'];

    protected $table = 'coupons';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'float', 'max_discount_bdt' => 'float', 'min_subtotal_bdt' => 'float', 'active' => 'boolean',
            'starts_at' => 'datetime', 'expires_at' => 'datetime', 'max_uses' => 'integer', 'used' => 'integer',
            'per_customer_limit' => 'integer', 'first_order_only' => 'boolean', 'allowed_phones' => 'array', 'featured' => 'boolean',
        ];
    }

    /** Orders that used this coupon (any status). */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'coupon_id');
    }

    /** Active, inside its schedule and with uses left. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('used', '<', 'max_uses'));
    }

    /** Atomically uses one redemption: false when the coupon is inactive, outside its schedule or has no uses left. */
    public static function claim(int $id): bool
    {
        return static::query()->whereKey($id)->live()->increment('used') === 1;
    }

    /** Gives a use back (order cancelled). Never below zero. */
    public static function release(int $id): void
    {
        static::query()->whereKey($id)->where('used', '>', 0)->decrement('used');
    }

    public function usable(): bool
    {
        return $this->active && $this->started() && ! $this->expired()
            && ($this->max_uses === null || $this->used < $this->max_uses);
    }

    public function started(): bool
    {
        return ! $this->starts_at || ! $this->starts_at->isFuture();
    }

    public function expired(): bool
    {
        return $this->expires_at !== null && ! $this->expires_at->isFuture();
    }

    public function forStore(): ?string
    {
        return $this->store && $this->store !== 'all' ? $this->store : null;
    }

    /** @return list<string> normalised +8801… numbers; empty = everyone */
    public function phoneList(): array
    {
        return array_values(array_filter(array_map(fn ($p) => Phone::normalize((string) $p), (array) ($this->allowed_phones ?? []))));
    }

    /** Needs a signed-in shopper to check (allowed phones, first order, per-customer limit). */
    public function needsCustomer(): bool
    {
        return $this->phoneList() !== [] || $this->first_order_only || $this->per_customer_limit !== null;
    }
}
