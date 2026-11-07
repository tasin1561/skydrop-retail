<?php

namespace App\Services\System;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Storefront maintenance mode (Admin → System → Settings). Not Laravel's `artisan down`: the API and admin keep
 * working; web and mobile read GET /features → maintenance {on, message, since} and show a "We'll be right back" screen.
 */
class Maintenance
{
    public const DEFAULT_MESSAGE = "We're doing some quick maintenance and will be back shortly. Thank you for your patience!";

    private const CACHE_KEY = 'system:maintenance';

    /** @return array{on: bool, message: string, since: ?string} */
    public static function state(): array
    {
        try {
            $v = Cache::remember(self::CACHE_KEY, 60, fn () => (array) Setting::get('system.maintenance', []));
        } catch (Throwable) {
            $v = [];
        }
        $on = (bool) ($v['on'] ?? false);
        $message = trim((string) ($v['message'] ?? ''));

        return ['on' => $on, 'message' => $message !== '' ? $message : self::DEFAULT_MESSAGE, 'since' => $on ? ($v['since'] ?? null) : null];
    }

    public static function on(): bool
    {
        return self::state()['on'];
    }

    public static function set(bool $on, ?string $message = null): void
    {
        $current = (array) Setting::get('system.maintenance', []);
        Setting::put('system.maintenance', [
            'on' => $on,
            'message' => mb_substr(trim((string) ($message ?? ($current['message'] ?? ''))), 0, 500),
            'since' => $on ? (! empty($current['on']) ? ($current['since'] ?? now()->toIso8601String()) : now()->toIso8601String()) : null,
        ]);
        Cache::forget(self::CACHE_KEY);
    }
}
