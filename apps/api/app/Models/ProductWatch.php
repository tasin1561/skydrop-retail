<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductWatch extends Model
{
    protected $table = 'product_watches';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_bdt' => 'float', 'baseline_bdt' => 'float', 'active' => 'boolean', 'notified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => (string) $this->id, 'productId' => $this->product_id, 'type' => $this->type,
            'targetBdt' => $this->target_bdt !== null ? (int) $this->target_bdt : null, 'active' => $this->active,
            'notifiedAt' => $this->notified_at?->toIso8601String(), 'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
