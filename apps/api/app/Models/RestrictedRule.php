<?php

namespace App\Models;

use App\Services\Cms\CmsCache;
use Illuminate\Database\Eloquent\Model;

class RestrictedRule extends Model
{
    public const TYPES = ['keyword' => 'Keyword in title', 'category' => 'Category', 'brand' => 'Brand'];

    public const ACTIONS = ['block' => 'Block (cannot order)', 'warn' => 'Warn (info only)', 'surcharge' => 'Surcharge (added to price)'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'surcharge_bdt' => 'integer', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CmsCache::flush());
        static::deleted(fn () => CmsCache::flush());
    }
}
