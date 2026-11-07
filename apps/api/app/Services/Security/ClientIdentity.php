<?php

namespace App\Services\Security;

use Illuminate\Http\Request;

/** Rate-limit identities: IP, signed-in user and a device fingerprint. */
class ClientIdentity
{
    public static function ip(Request $request): string
    {
        // Next.js server (valid X-Internal-Key): the shopper's IP it forwards in X-Client-IP, so limits and blocks hit the
        // shopper, not the storefront server.
        if ($forwarded = self::forwardedShopperIp($request)) {
            return $forwarded;
        }

        // Cloudflare in front: CF-Connecting-IP is the client (set TrustProxies / only accept it from Cloudflare ranges at deploy).
        return (string) ($request->header('CF-Connecting-IP') && config('security.trust_cf_header', false) ? $request->header('CF-Connecting-IP') : $request->ip());
    }

    /**
     * The shopper IP the Next.js server forwards (X-Client-IP), trusted only with a valid X-Internal-Key and only when it is
     * a public, routable address (never private, loopback, reserved or empty: those would be the server or its network).
     */
    public static function forwardedShopperIp(Request $request): ?string
    {
        if (! AppSignature::isInternal($request)) {
            return null;
        }
        $ip = trim((string) $request->header('X-Client-IP'));

        return self::isPublicIp($ip) ? $ip : null;
    }

    public static function isPublicIp(?string $ip): bool
    {
        return $ip !== null && $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
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
