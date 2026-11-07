<?php

namespace App\Support;

use App\Models\Marketplace;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Keeps the "store.{id}" feature switches and marketplaces.enabled in step. Each side writes the other without
 * firing its events, so there is no loop.
 */
class FeatureSync
{
    /** Called by Features::set() after a switch changes. */
    public static function fromFeature(string $key, bool $on): void
    {
        if (! str_starts_with($key, 'store.')) {
            return;
        }
        try {
            $id = substr($key, 6);
            if (Marketplace::query()->whereKey($id)->update(['enabled' => $on, 'updated_at' => now()])) {
                Cache::forget("catalog:live:$id");
            }
        } catch (Throwable) {
            // marketplaces table missing (fresh install): nothing to sync
        }
    }

    /** Called when a marketplace is switched on/off (Marketplaces resource, catalog commands). */
    public static function fromMarketplace(Marketplace $m): void
    {
        $key = 'store.'.$m->id;
        if (! array_key_exists($key, Features::REGISTRY)) {
            return;
        }
        Setting::put('features.'.$key, (bool) $m->enabled);
        Features::flush();
        Cache::forget('catalog:live:'.$m->id);
    }
}
