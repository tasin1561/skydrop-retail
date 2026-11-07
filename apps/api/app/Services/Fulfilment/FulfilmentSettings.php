<?php

namespace App\Services\Fulfilment;

use App\Models\Setting;
use Throwable;

/** Runtime fulfilment settings: settings table "fulfilment.{key}" first, then config/fulfilment.php. */
class FulfilmentSettings
{
    public const KEYS = [
        'hub.weight_variance_pct', 'unboxing.fee_bdt', 'unboxing.per', 'unboxing.auto_approve_hours',
        'returns.window_days', 'returns.changed_mind', 'returns.shipping_fee_bdt', 'price_alerts.rise_pct',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = config('fulfilment.'.$key, $default);
        try {
            return Setting::get('fulfilment.'.$key, $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    public static function put(string $key, mixed $value): void
    {
        Setting::put('fulfilment.'.$key, $value);
    }

    public static function float(string $key): float
    {
        return (float) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return filter_var(self::get($key), FILTER_VALIDATE_BOOL);
    }
}
