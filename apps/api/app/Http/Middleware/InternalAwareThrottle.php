<?php

namespace App\Http\Middleware;

use App\Services\Security\AppSignature;
use App\Services\Security\ClientIdentity;
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
    /** Without a usable X-Client-IP all Next.js traffic shares one bucket, so it gets this many times the per-shopper limit. */
    public const SHARED_MULTIPLIER = 20;

    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        if ($request instanceof Request && AppSignature::isInternal($request)) {
            // The Next.js server forwards the shopper IP (X-Client-IP): the route limit applies per shopper, in a bucket of
            // its own ("internal:" prefix). No/unusable header: one shared bucket for the server with a higher ceiling.
            $shopper = ClientIdentity::forwardedShopperIp($request);
            $max = $shopper ? $maxAttempts : (int) $maxAttempts * self::SHARED_MULTIPLIER;

            return parent::handle($request, $next, $max, $decayMinutes, 'internal:'.($shopper ?? 'shared').':'.$prefix);
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }

    /** Signed-in user, else the real client IP (CF-Connecting-IP when trusted), not the proxy's address. */
    protected function resolveRequestSignature($request)
    {
        if ($request instanceof Request && ! $request->user() && $route = $request->route()) {
            return $this->formatIdentifier($route->getDomain().'|'.ClientIdentity::ip($request));
        }

        return parent::resolveRequestSignature($request);
    }
}
