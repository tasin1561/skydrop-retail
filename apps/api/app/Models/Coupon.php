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

    public function usable(): bool
    {
        return $this->active
            && (! $this->expires_at || $this->expires_at->isFuture())
            && ($this->max_uses === null || $this->used < $this->max_uses);
    }
}
