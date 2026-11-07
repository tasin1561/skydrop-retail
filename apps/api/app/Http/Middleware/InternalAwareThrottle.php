<?php

namespace App\Http\Middleware;

use App\Services\Security\AppSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's "throttle" middleware, except for the Next.js server (valid X-Internal-Key): all storefront traffic
 * arrives from that one IP, so per-IP route throttles would starve every shopper. Shoppers are rate-limited in front
 * of Next.js by Cloudflare, and catalog.guard keeps a high global ceiling for the internal key.
 */
class InternalAwareThrottle extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        if ($request instanceof Request && AppSignature::isInternal($request)) {
            return $next($request);
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }
}
