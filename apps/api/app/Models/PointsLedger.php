<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsLedger extends Model
{
    protected $table = 'points_ledger';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['points' => 'integer', 'expires_at' => 'datetime', 'settled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
