<?php

namespace App\Models;

use App\Services\Cms\CmsCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    public const STORES = ['all', 'amazon', 'flipkart', 'myntra'];

    public const PLACEMENTS = ['hero' => 'Hero carousel', 'strip' => 'Promo strip', 'category' => 'Category tile'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'active' => 'boolean', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CmsCache::flush());
        static::deleted(fn () => CmsCache::flush());
    }

    /** Active and inside its schedule right now. */
    public function scopeLive(Builder $q): Builder
    {
        $now = now();

        return $q->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->image_url ?: null;
    }

    public function toApi(): array
    {
        return [
            'id' => (string) $this->id, 'title' => $this->title, 'subtitle' => $this->subtitle, 'image' => $this->imageUrl(),
            'link' => $this->link, 'cta' => $this->cta, 'store' => $this->store, 'placement' => $this->placement,
            'startsAt' => $this->starts_at?->toIso8601String(), 'endsAt' => $this->ends_at?->toIso8601String(), 'sort' => $this->sort,
        ];
    }
}
