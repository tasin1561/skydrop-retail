<?php

namespace App\Services\Security;

use Illuminate\Http\Request;

/** Rate-limit identities: IP, signed-in user and a device fingerprint. */
class ClientIdentity
{
    public static function ip(Request $request): string
    {
        // Cloudflare in front: CF-Connecting-IP is the client (set TrustProxies / only accept it from Cloudflare ranges at deploy).
        return (string) ($request->header('CF-Connecting-IP') && config('security.trust_cf_header', true) ? $request->header('CF-Connecting-IP') : $request->ip());
    }

    public static function device(Request $request): string
    {
        $explicit = (string) $request->header('X-Device-Id');
        if ($explicit !== '') {
            return 'd:'.substr(hash('sha256', $explicit), 0, 24);
        }

        return 'f:'.substr(hash('sha256', implode('|', [
            $request->userAgent(), $request->header('Accept-Language'), $request->header('Accept-Encoding'), $request->header('Sec-CH-UA-Platform'),
        ])), 0, 24);
    }

    public static function userId(Request $request): ?string
    {
        try {
            $u = $request->user('sanctum');
        } catch (\Throwable) {
            $u = null;
        }

        return $u ? (string) $u->getAuthIdentifier() : null;
    }
}
