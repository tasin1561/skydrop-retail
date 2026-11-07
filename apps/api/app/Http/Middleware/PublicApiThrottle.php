<?php

namespace App\Http\Middleware;

use App\Services\Security\AppSignature;
use App\Services\Security\ClientIdentity;
use App\Services\System\HealthStats;
use App\Support\Limits;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outermost "api" middleware (prepended to the group):
 *  - default per-IP ceiling for every /api/v1 request (System → Limits → api.per_ip_per_minute), on top of the
 *    per-route throttles; the Next.js server (X-Internal-Key), webhooks and health checks are exempt;
 *  - counts every 429 the API answers (any limiter) for System → Health → Security.
 */
class PublicApiThrottle
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('security.enforce') && ! $this->exempt($request)) {
            $max = max(1, Limits::int('api.per_ip_per_minute'));
            $key = 'api:ip:'.ClientIdentity::ip($request);
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $retry = RateLimiter::availableIn($key);
                HealthStats::count429();

                return response()->json(['message' => 'Too many requests. Slow down.', 'code' => 'rate_limited', 'retryAfter' => $retry], 429)
                    ->withHeaders(['Retry-After' => $retry, 'X-RateLimit-Limit' => $max, 'X-RateLimit-Remaining' => 0]);
            }
            RateLimiter::hit($key, 60);
        }

        $response = $next($request);
        if ($response->getStatusCode() === 429) {
            HealthStats::count429();
        }

        return $response;
    }

    private function exempt(Request $request): bool
    {
        return $request->is('api/v1/webhooks/*', 'api/v1/health', 'api/v1/health/*') || AppSignature::isInternal($request);
    }
}
