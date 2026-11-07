<?php

namespace App\Services\Security;

use Illuminate\Http\Request;

/**
 * HMAC request signing for app clients (apps/mobile/src/api/sign.ts):
 *   canonical = "{timestamp}\n{METHOD}\n{path}\n{query}"   path = "/api/v1/…", query = raw query string without "?"
 *   X-App-Key: <key id>   X-App-Timestamp: <unix seconds>   X-App-Signature: hex(hmac_sha256(secret, canonical))
 * The secret ships inside the app, so this raises the bar (no copy-paste curl, 5-minute replay window) rather than
 * proving identity; see docs/SECURITY.md.
 */
class AppSignature
{
    public static function canonical(string $timestamp, string $method, string $path, string $query): string
    {
        return $timestamp."\n".strtoupper($method)."\n".$path."\n".$query;
    }

    public static function sign(string $secret, string $timestamp, string $method, string $path, string $query = ''): string
    {
        return hash_hmac('sha256', self::canonical($timestamp, $method, $path, $query), $secret);
    }

    /** True when the request carries a valid, fresh signature from a configured app key. */
    public static function verify(Request $request): bool
    {
        $id = (string) $request->header('X-App-Key');
        $ts = (string) $request->header('X-App-Timestamp');
        $sig = strtolower((string) $request->header('X-App-Signature'));
        $secret = config('security.app_keys')[$id] ?? null;
        if (! $secret || ! ctype_digit($ts) || $sig === '') {
            return false;
        }
        if (abs(time() - (int) $ts) > (int) config('security.signature_skew', 300)) {
            return false;
        }
        $expected = self::sign($secret, $ts, $request->method(), '/'.ltrim($request->path(), '/'), (string) $request->server('QUERY_STRING', ''));

        return hash_equals($expected, $sig);
    }

    public static function isInternal(Request $request): bool
    {
        $key = (string) config('security.internal_key');

        return $key !== '' && hash_equals($key, (string) $request->header('X-Internal-Key'));
    }
}
