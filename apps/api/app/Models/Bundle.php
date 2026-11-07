<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Admin-curated bundle: a fixed set of products with an extra discount (funded from margin). */
class Bundle extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['discount_pct' => 'float', 'active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class)->orderBy('sort')->orderBy('id');
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->where('active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
