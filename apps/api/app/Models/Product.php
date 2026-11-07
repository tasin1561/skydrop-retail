<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_inr' => 'float', 'mrp_inr' => 'float', 'rating' => 'float', 'reviews' => 'integer',
            'prime' => 'boolean', 'best_seller' => 'boolean', 'amazons_choice' => 'boolean', 'in_stock' => 'boolean',
            'hidden' => 'boolean', 'detailed' => 'boolean', 'demo' => 'boolean', 'pincode_verified' => 'boolean',
            'placeholder' => 'array', 'price_checked_at' => 'datetime', 'details_fetched_at' => 'datetime',
            'coupon_inr' => 'float', 'effective_price_inr' => 'float', 'saving_inr' => 'float', 'deal_ends_at' => 'datetime',
        ];
    }

    public function details(): HasOne
    {
        return $this->hasOne(ProductDetail::class, 'product_id');
    }

}
