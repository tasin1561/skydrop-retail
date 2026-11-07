<?php

namespace App\Services\Catalog;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runtime settings: an admin override from the settings table, else config('retail.<key>').
 * Keys use config dot notation, e.g. "ttl.search_fresh", "oxylabs.monthly_budget_usd".
 */
class RetailSettings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = config("retail.$key", $default);
        try {
            $value = Cache::remember("retail-setting:$key", 60, fn () => ['v' => Setting::get($key)])['v'];
        } catch (Throwable) {
            return $fallback; // settings table missing (fresh install) – use config
        }

        return $value ?? $fallback;
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function float(string $key, float $default = 0): float
    {
        return (float) self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /** Minutes for a ttl.* key. */
    public static function ttl(string $name): int
    {
        return self::int("ttl.$name");
    }
}
