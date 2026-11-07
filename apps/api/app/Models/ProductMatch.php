<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Two listings of the same product (compare stores). product_id < match_id. */
class ProductMatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'match_id');
    }
}
