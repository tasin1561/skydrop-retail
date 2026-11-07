<?php

namespace App\Services\Messaging;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Runtime messaging settings: settings table key "messaging.<path>" first, then config("messaging.<path>").
 * Secrets (API keys) are stored encrypted.
 */
class MessagingSettings
{
    public const SECRETS = ['sms.bulksmsbd.api_key', 'sms.sslwireless.api_token', 'sms.alpha.api_key'];

    public static function get(string $path, mixed $default = null): mixed
    {
        $fallback = config('messaging.'.$path, $default);
        try {
            $value = Cache::remember('setting:messaging.'.$path, 300, fn () => Setting::get('messaging.'.$path));
        } catch (Throwable) {
            return $fallback;
        }
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (in_array($path, self::SECRETS, true) && is_string($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                return $fallback;
            }
        }

        return $value;
    }

    public static function put(string $path, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            Setting::query()->whereKey('messaging.'.$path)->delete();
        } else {
            if (in_array($path, self::SECRETS, true)) {
                $value = Crypt::encryptString((string) $value);
            }
            Setting::put('messaging.'.$path, $value);
        }
        Cache::forget('setting:messaging.'.$path);
    }

    /** Ordered SMS driver chain (failover). */
    public static function smsChain(): array
    {
        $chain = self::get('sms.failover', []);
        if (is_string($chain)) {
            $chain = array_filter(array_map('trim', explode(',', $chain)));
        }
        $chain = array_values(array_unique(array_filter((array) $chain)));

        return $chain ?: [(string) config('retail.sms.driver', 'log')];
    }
}
