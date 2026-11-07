<?php

namespace App\Models\Fulfilment;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HubReceipt extends Model
{
    public const CONDITIONS = ['ok' => 'OK', 'damaged' => 'Damaged', 'wrong_item' => 'Wrong item'];

    protected $table = 'hub_receipts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['photos' => 'array', 'weight_kg' => 'float', 'length_cm' => 'float', 'width_cm' => 'float', 'height_cm' => 'float', 'received_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return array<int, string> public URLs */
    public function photoUrls(): array
    {
        return array_values(array_map(fn ($p) => Storage::disk('public')->url($p), (array) $this->photos));
    }

    public function videoUrl(): ?string
    {
        return $this->video ? Storage::disk('public')->url($this->video) : null;
    }

    public function dims(): ?string
    {
        return $this->length_cm ? "{$this->length_cm}×{$this->width_cm}×{$this->height_cm} cm" : null;
    }
}
