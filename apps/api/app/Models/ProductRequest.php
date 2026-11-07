<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Request a product": a shopper asks us to source something we do not list. */
class ProductRequest extends Model
{
    public const STATUSES = ['new' => 'New', 'sourcing' => 'Sourcing', 'quoted' => 'Quoted', 'ordered' => 'Ordered', 'declined' => 'Declined'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'budget_bdt' => 'integer', 'quote_bdt' => 'float', 'quoted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quoteOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'quote_order_id');
    }

    public function toApi(): array
    {
        $order = $this->quote_order_id ? $this->quoteOrder : null;

        return [
            'id' => (string) $this->id, 'status' => $this->status, 'url' => $this->url, 'description' => $this->description,
            'photoUrl' => $this->photo_url, 'qty' => $this->qty, 'budgetBdt' => $this->budget_bdt, 'market' => $this->market,
            'productId' => $this->product_id, 'quoteBdt' => $this->quote_bdt !== null ? (int) round($this->quote_bdt) : null,
            'quoteNote' => $this->quote_note, 'orderId' => $this->quote_order_id,
            'payUrl' => $order && $order->balanceBdt() > 0 && $order->status !== 'cancelled' ? '/orders/'.$order->id.'?pay=1' : null,
            'quotedAt' => $this->quoted_at?->toIso8601String(), 'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
