<?php

namespace App\Models\Fulfilment;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Work for the Kolkata hub team, e.g. return a damaged / wrong item to the seller. */
class HubTask extends Model
{
    public const TYPES = ['return_to_seller' => 'Return to seller'];

    protected $table = 'hub_tasks';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
