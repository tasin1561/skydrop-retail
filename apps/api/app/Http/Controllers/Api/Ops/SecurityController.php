<?php

namespace App\Http\Controllers\Api\Ops;

use App\Http\Controllers\Controller;
use App\Services\Audit\ActivityLogger;
use App\Services\Security\AppSignature;
use App\Services\Security\Blocklist;
use App\Services\Security\ClientIdentity;
use App\Services\Security\Turnstile;
use App\Support\Qr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SecurityController extends Controller
{
    /**
     * Honeypot: GET /products/export and the hidden honeypot product. No real client ever calls these, so the caller
     * is a scraper: block its IP for 24 h and log it. Via the Next.js server (internal key) the shopper IP comes in X-Client-IP.
     */
    public function honeypot(Request $request): JsonResponse
    {
        // Via the Next.js server only a public, routable X-Client-IP is blocked: a missing or private value would be the
        // storefront server itself (or its network), and blocking that would take the whole shop down.
        $ip = AppSignature::isInternal($request) ? (string) ClientIdentity::forwardedShopperIp($request) : ClientIdentity::ip($request);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $hours = (int) config('security.honeypot.block_hours', 24);
            Blocklist::block('ip', $ip, 'Honeypot: '.$request->path(), 'honeypot', $hours);
            ActivityLogger::log('security.honeypot', null, [], "Honeypot hit from {$ip}; blocked for {$hours} h", [
                'ip' => $ip, 'path' => $request->path(), 'ua' => mb_substr((string) $request->userAgent(), 0, 200),
            ]);
        }

        return response()->json(['message' => 'Not found.'], 404);
    }

    /** POST /security/challenge {token}: a solved Turnstile clears this IP of bot challenges for an hour. */
    public function challenge(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        $ok = Turnstile::verify($data['token'], ClientIdentity::ip($request));

        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }

    /** GET /qr?text=<our URL> → SVG QR code (share buttons). */
    public function qr(Request $request): Response
    {
        $text = (string) $request->query('text', '');
        abort_if($text === '' || mb_strlen($text) > 600 || ! Qr::allowed($text), 422, 'Only Skydrop Retail links can be encoded.');

        return response(Qr::svg($text, (int) min(10, max(2, (int) $request->query('scale', 5)))), 200, [
            'Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }
}
