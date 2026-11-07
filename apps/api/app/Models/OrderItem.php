<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['placeholder' => 'array', 'unit_bdt' => 'float', 'unit_inr' => 'float', 'weight_kg' => 'float', 'qty' => 'integer', 'fx_rate' => 'float',
            // fulfilment module
            'hub_received_at' => 'datetime', 'price_alert' => 'array', 'price_checked_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** Web `CartItem` shape. */
    public function toApi(): array
    {
        return [
            'productId' => $this->product_id, 'market' => $this->market, 'title' => $this->title, 'image' => $this->image,
            'placeholder' => $this->placeholder, 'variant' => $this->variant, 'unitBdt' => (int) round($this->unit_bdt),
            'weightKg' => (float) $this->weight_kg, 'qty' => (int) $this->qty,
        ];
    }
}
