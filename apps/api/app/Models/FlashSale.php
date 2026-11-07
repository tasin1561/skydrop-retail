<?php

namespace App\Models;

use App\Services\Cms\CmsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'active' => 'boolean', 'max_qty' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CmsCache::flush());
        static::deleted(fn () => CmsCache::flush());
    }

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class)->orderBy('sort')->orderBy('id');
    }

    public function isLive(): bool
    {
        return $this->active && $this->starts_at && $this->ends_at && $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }

    public function status(): string
    {
        if (! $this->active) {
            return 'off';
        }
        if ($this->ends_at && $this->ends_at->lte(now())) {
            return 'ended';
        }

        return $this->starts_at && $this->starts_at->gt(now()) ? 'upcoming' : 'live';
    }
}
