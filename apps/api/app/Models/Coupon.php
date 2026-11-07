<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'coupons';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'float', 'max_discount_bdt' => 'float', 'min_subtotal_bdt' => 'float', 'active' => 'boolean',
            'expires_at' => 'datetime', 'max_uses' => 'integer', 'used' => 'integer',
        ];
    }

    /** Atomically uses one redemption: false when the coupon is inactive, expired or has no uses left. */
    public static function claim(int $id): bool
    {
        return static::query()->whereKey($id)->where('active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('used', '<', 'max_uses'))
            ->increment('used') === 1;
    }

    public function usable(): bool
    {
        return $this->active
            && (! $this->expires_at || $this->expires_at->isFuture())
            && ($this->max_uses === null || $this->used < $this->max_uses);
    }
}
