<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'amount_bdt' => 'float', 'refunded_bdt' => 'float', 'paid_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Merge extra data into the JSON payload. */
    public function remember(array $data): void
    {
        $this->payload = array_merge($this->payload ?? [], $data);
    }
}
