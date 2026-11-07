<?php

namespace App\Services\Fraud\Couriers;

use App\Support\Knobs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Courier credentials and login sessions for the fraud check.
 *
 * Credentials: settings table "finance.fraud.couriers.{id}.{field}" (edited on Admin → System → Finance & security,
 * secrets encrypted at rest), falling back to config/finance.php (.env).
 * Login tokens are cached encrypted, so a cache dump never exposes a usable courier session.
 */
class CourierCreds
{
    /** @return array<string, mixed> */
    public static function get(string $courier): array
    {
        $base = (array) config("finance.fraud.couriers.$courier", []);
        $out = [];
        foreach ($base as $field => $default) {
            $out[$field] = Knobs::get("finance.fraud.couriers.$courier.$field", $default);
        }
        $out['enabled'] = filter_var($out['enabled'] ?? false, FILTER_VALIDATE_BOOL);

        return $out;
    }

    public static function cachedSession(string $key): ?array
    {
        try {
            $v = Cache::get($key);

            return is_string($v) ? (array) json_decode(Crypt::decryptString($v), true) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function putSession(string $key, array $session, int $minutes): void
    {
        Cache::put($key, Crypt::encryptString((string) json_encode($session)), now()->addMinutes($minutes));
    }

    public static function forgetSession(string $key): void
    {
        Cache::forget($key);
    }

    /** "01XXXXXXXXX" from any BD format (+8801…, 8801…, 01…). */
    public static function localPhone(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($d, '880')) {
            $d = substr($d, 2);
        }

        return $d;
    }
}
