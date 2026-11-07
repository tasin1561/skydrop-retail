<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** HSTS, framing, sniffing, referrer and permissions headers on every response (API and admin). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        if (config('security.headers.hsts') && $request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->is('api/*')) {
            // JSON only: nothing may render or frame it.
            $h->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        } elseif (! $h->has('Content-Security-Policy')) {
            $h->set('Content-Security-Policy', "frame-ancestors 'none'");
        }
        $h->remove('X-Powered-By');

        return $response;
    }
}
