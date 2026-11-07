<?php

namespace App\Http\Middleware;

use App\Services\Security\ClientIdentity;
use App\Support\Limits;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per IP + per user + per device rate limits (Laravel RateLimiter) with progressive slowdown before the 429.
 * Strict buckets: search, suggest, resolve, product detail and price history.
 */
class CatalogThrottle
{
    public const STRICT = ['api/v1/search', 'api/v1/suggest', 'api/v1/products/resolve', 'api/v1/products/{id}'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.enforce')) {
            return $next($request);
        }
        $l = config('security.limits');
        $uri = $request->route()?->uri() ?? $request->path();

        if ($request->attributes->get('client') === 'internal') {
            $buckets = ['internal' => $l['internal']];
        } else {
            $ip = ClientIdentity::ip($request);
            $strict = in_array($uri, self::STRICT, true) || str_contains($uri, 'price-history') || str_contains($uri, 'history');
            $buckets = ['ip:'.$ip => $l['catalog_ip']];
            if ($strict) {
                // System → Limits: searches / product views / paste-link resolves per IP per minute (defaults: config strict_ip, resolve_ip).
                $kind = match (true) {
                    str_ends_with($uri, 'resolve') => 'resolves',
                    in_array($uri, ['api/v1/search', 'api/v1/suggest'], true) => 'searches',
                    default => 'product_views',
                };
                $buckets["strict:ip:$kind:".$ip] = Limits::int("catalog.{$kind}_per_min");
                $buckets['strict:dev:'.ClientIdentity::device($request)] = $l['strict_device'];
                if ($uid = ClientIdentity::userId($request)) {
                    $buckets['strict:user:'.$uid] = $l['strict_user'];
                }
            }
        }

        $worst = 0.0;
        foreach ($buckets as $key => $max) {
            $key = 'catalog:'.$key;
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $retry = RateLimiter::availableIn($key);

                return response()->json(['message' => 'Too many requests. Slow down.', 'code' => 'rate_limited', 'retryAfter' => $retry], 429)
                    ->withHeaders(['Retry-After' => $retry, 'X-RateLimit-Limit' => $max, 'X-RateLimit-Remaining' => 0]);
            }
            $hits = RateLimiter::hit($key, 60);
            $worst = max($worst, $hits / max(1, $max));
        }

        // Progressive slowdown: past 75% of a limit, each request waits longer (up to ~2 s) before the hard 429.
        $after = (float) $l['slowdown_after'];
        if ($worst > $after && ! app()->runningUnitTests()) {
            usleep((int) min(2000, $l['slowdown_ms'] * (($worst - $after) / (1 - $after)) * 4) * 1000);
        }

        return $next($request);
    }
}
