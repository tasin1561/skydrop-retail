<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * Runtime knobs for the finance and security modules: settings table first (key "finance.x" / "security.x"),
 * then config/finance.php or config/security.php. Edited on Admin → System → Finance & security.
 */
class Knobs
{
    /** @var array<string, mixed> */
    private static array $memo = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $fallback = config($key, $default);
        try {
            $value = Setting::get($key, $fallback);
        } catch (Throwable) {
            $value = $fallback;
        }

        return self::$memo[$key] = $value ?? $fallback;
    }

    public static function float(string $key, float $default = 0): float
    {
        return (float) self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOL);
    }

    public static function put(string $key, mixed $value): void
    {
        if ($value === null || $value === '') {
            Setting::query()->whereKey($key)->delete();
        } else {
            Setting::put($key, $value);
        }
        self::forget();
    }

    public static function forget(): void
    {
        self::$memo = [];
    }
}
