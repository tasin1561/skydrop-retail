<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Marks crawler requests (request attribute "crawler") so catalog reads never trigger provider fetches for bots. */
class DetectCrawler
{
    private const PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|facebot|embedly|preview|whatsapp|telegram|discord|semrush|ahrefs|mj12|bingpreview|headless|lighthouse|python-requests|curl\/|wget|go-http-client|scrapy/i';

    public static function isCrawler(?string $userAgent): bool
    {
        return $userAgent !== null && $userAgent !== '' && (bool) preg_match(self::PATTERN, $userAgent);
    }

    public function handle(Request $request, Closure $next): Response
    {
        // The Next.js storefront renders server-side and forwards the shopper's agent as X-Client-UA when it can.
        $ua = $request->header('X-Client-UA') ?: $request->userAgent();
        $request->attributes->set('crawler', self::isCrawler($ua));

        return $next($request);
    }
}
