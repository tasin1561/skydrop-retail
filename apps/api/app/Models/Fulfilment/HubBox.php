<?php

namespace App\Models\Fulfilment;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HubBox extends Model
{
    protected $table = 'hub_boxes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['weight_kg' => 'float', 'item_count' => 'integer', 'packed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(HubReceipt::class, 'box_id');
    }
}
