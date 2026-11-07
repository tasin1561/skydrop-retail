<?php

namespace App\Models;

use App\Services\Cms\CmsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['off_pct' => 'float', 'off_bdt' => 'integer', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CmsCache::flush());
        static::deleted(fn () => CmsCache::flush());
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
