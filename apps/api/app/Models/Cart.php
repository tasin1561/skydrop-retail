<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Server copy of a signed-in shopper's cart (abandoned-cart reminders). */
class Cart extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'items' => 'array', 'total_bdt' => 'integer', 'item_count' => 'integer',
            'reminded_1h_at' => 'datetime', 'reminded_24h_at' => 'datetime', 'converted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
