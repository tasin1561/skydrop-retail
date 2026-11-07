<?php

namespace App\Services\Money;

use App\Models\Setting;
use Throwable;

/**
 * Runtime settings of the money module: settings table key "money.{key}" first, then config/money.php.
 * Edited on the admin page App\Filament\Pages\MoneySettings.
 */
class MoneySettings
{
    /** Keys edited on the admin page (scalars and arrays). */
    public const KEYS = [
        'wallet.refund_default', 'wallet.refund_bonus_pct',
        'gift_cards.presets', 'gift_cards.min_bdt', 'gift_cards.max_bdt', 'gift_cards.expiry_months',
        'membership.reminder_days', 'membership.credit_expiry_days',
        'emi.enabled', 'emi.min_order_bdt', 'emi.tenures', 'emi.banks',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = config('money.'.$key, $default);
        try {
            $v = Setting::get('money.'.$key);
        } catch (Throwable) {
            return $fallback;
        }

        return $v === null || $v === '' ? $fallback : $v;
    }

    public static function put(string $key, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            Setting::query()->whereKey('money.'.$key)->delete();

            return;
        }
        Setting::put('money.'.$key, $value);
    }

    public static function float(string $key): float
    {
        return (float) self::get($key, 0);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key, 0);
    }
}
