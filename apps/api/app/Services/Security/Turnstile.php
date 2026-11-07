<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Cloudflare Turnstile. Disabled (always passes) while TURNSTILE_SECRET_KEY is unset. */
class Turnstile
{
    public static function enabled(): bool
    {
        return filled(config('security.turnstile.secret'));
    }

    public static function verify(?string $token, ?string $ip): bool
    {
        if (! self::enabled()) {
            return true;
        }
        if (! $token) {
            return false;
        }
        try {
            $r = Http::asForm()->timeout(6)->post(config('security.turnstile.verify_url'), array_filter([
                'secret' => config('security.turnstile.secret'), 'response' => $token, 'remoteip' => $ip,
            ]));
            $ok = (bool) $r->json('success');
        } catch (Throwable) {
            $ok = false;
        }
        if ($ok && $ip) {
            Cache::put('turnstile:pass:'.$ip, true, (int) config('security.turnstile.pass_ttl', 3600));
        }

        return $ok;
    }

    /** A challenge solved recently from this IP. */
    public static function passed(?string $ip): bool
    {
        return $ip !== null && (bool) Cache::get('turnstile:pass:'.$ip);
    }
}
