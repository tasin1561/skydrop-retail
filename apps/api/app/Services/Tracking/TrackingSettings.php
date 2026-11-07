<?php

namespace App\Services\Tracking;

use App\Services\Catalog\RetailSettings;

/**
 * Tracking settings: Admin → Settings → Tracking (settings table, key "tracking.*") first, then config/retail.php.
 */
final class TrackingSettings
{
    public const DRIVERS = ['off', 'delivro', 'direct', 'both'];

    public const PURCHASE_ON = ['paid', 'placed', 'delivered'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $v = RetailSettings::get('tracking.'.$key, $default);

        return is_string($v) ? trim($v) : $v;
    }

    public static function str(string $key): string
    {
        return (string) (self::get($key) ?? '');
    }

    public static function driver(): string
    {
        $d = strtolower(self::str('driver'));

        return in_array($d, self::DRIVERS, true) ? $d : 'off';
    }

    public static function purchaseOn(): string
    {
        $p = strtolower(self::str('purchase_on'));

        return in_array($p, self::PURCHASE_ON, true) ? $p : 'paid';
    }

    public static function sendDelivered(): bool
    {
        return filter_var(self::get('send_delivered', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** Public ids for the storefront (GET /tracking/config). Never includes secrets. */
    public static function publicConfig(): array
    {
        return [
            'metaPixelId' => self::str('meta.pixel_id') ?: null,
            'ga4Id' => self::str('ga4.measurement_id') ?: null,
            'tiktokPixelId' => self::str('tiktok.pixel_id') ?: null,
            'gtmId' => self::str('gtm_id') ?: null,
            'server' => self::driver() !== 'off', // the storefront mirrors events to POST /track only when true
        ];
    }
}
