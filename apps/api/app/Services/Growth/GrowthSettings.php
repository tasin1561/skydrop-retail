<?php

namespace App\Services\Growth;

use App\Models\Setting;
use Throwable;

/**
 * Growth runtime settings: settings table (same dotted key) first, then config/growth.php.
 *  affiliate.rates.{category} | affiliate.rates.default | affiliate.cookie_days | affiliate.hold_days
 *  affiliate.min_payout_bdt | affiliate.max_per_order_bdt | referral.inviter_points | referral.friend_points
 */
class GrowthSettings
{
    /** Keys edited on the admin Growth settings page (category rates are added per category). */
    public const KEYS = [
        'affiliate.rates.default', 'affiliate.cookie_days', 'affiliate.hold_days', 'affiliate.min_payout_bdt',
        'affiliate.max_per_order_bdt', 'referral.inviter_points', 'referral.friend_points',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = config('growth.'.$key, $default);
        try {
            $v = Setting::get($key);
        } catch (Throwable) {
            return $fallback;
        }

        return $v === null || $v === '' ? $fallback : $v;
    }

    public static function int(string $key): int
    {
        return (int) self::get($key, 0);
    }

    /** Commission percent for a category (falls back to affiliate.rates.default, then 3%). */
    public static function rate(?string $category): float
    {
        if ($category) {
            $v = self::get('affiliate.rates.'.$category);
            if (is_numeric($v)) {
                return (float) $v;
            }
        }

        return (float) self::get('affiliate.rates.default', 3.0);
    }

    /** Category rates that differ from the default: { category: percent }. */
    public static function categoryRates(): array
    {
        $out = [];
        foreach ((array) config('growth.affiliate.rates', []) as $cat => $v) {
            if ($cat !== 'default') {
                $out[$cat] = (float) $v;
            }
        }
        try {
            Setting::query()->where('key', 'like', 'affiliate.rates.%')->get()->each(function (Setting $s) use (&$out) {
                $cat = substr($s->key, strlen('affiliate.rates.'));
                if ($cat !== 'default' && is_numeric($s->value)) {
                    $out[$cat] = (float) $s->value;
                }
            });
        } catch (Throwable) {
            // settings table unavailable: config only
        }

        return $out;
    }

    /** Public program rules (landing page and dashboard). */
    public static function program(): array
    {
        return [
            'defaultRate' => self::rate(null),
            'rates' => (object) self::categoryRates(),
            'cookieDays' => self::int('affiliate.cookie_days'),
            'holdDays' => self::int('affiliate.hold_days'),
            'minPayoutBdt' => self::int('affiliate.min_payout_bdt'),
            'maxPerOrderBdt' => self::int('affiliate.max_per_order_bdt'),
            'payoutMethods' => ['bkash', 'nagad'],
        ];
    }
}
