<?php

namespace App\Http\Middleware;

use App\Services\Audit\ActivityLogger;
use App\Services\Commerce\Phone;
use App\Services\Security\Blocklist;
use App\Services\Security\ClientIdentity;
use App\Services\Security\Turnstile;
use App\Support\Limits;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every API request (appended to the "api" group):
 *  - IP blocklist (honeypot hits, staff blocks)
 *  - OTP abuse protection on auth/otp/*: Turnstile on request (when configured), per-phone cooldown,
 *    per-IP verify limit and a phone lockout after N wrong codes across codes (OtpService already limits
 *    sends per phone/IP and attempts per code).
 */
class ApiShield
{
    public const CATALOG_LIKE = ['api/v1/products/{id}/price-history', 'api/v1/products/{id}/compare', 'api/v1/products/{id}/bought-together',
        'api/v1/products/{id}/bundles'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.enforce')) {
            return $next($request);
        }
        $ip = ClientIdentity::ip($request);
        if (! $request->is('api/v1/webhooks/*') && Blocklist::isBlocked('ip', $ip)) {
            return response()->json(['message' => 'Access denied.', 'code' => 'blocked'], 403);
        }

        if ($request->isMethod('post') && $request->is('api/v1/auth/otp/request')) {
            return $this->otpRequest($request, $next, $ip);
        }
        if ($request->isMethod('post') && $request->is('api/v1/auth/otp/verify')) {
            return $this->otpVerify($request, $next, $ip);
        }

        // Catalog-like reads that live in other route files (price history, compare, bundles) get the same guard.
        if ($request->isMethod('get') && in_array($request->route()?->uri(), self::CATALOG_LIKE, true)) {
            return (new Pipeline(app()))->send($request)
                ->through([CatalogGuard::class, BotGuard::class, CatalogThrottle::class])
                ->then($next);
        }

        return $next($request);
    }

    private function otpRequest(Request $request, Closure $next, string $ip): Response
    {
        $phone = Phone::normalize((string) $request->input('phone'));
        if ($phone && Blocklist::isBlocked('phone', $phone)) {
            return response()->json(['message' => 'This number cannot sign in. Please contact support.', 'code' => 'blocked'], 403);
        }
        if ($phone && RateLimiter::tooManyAttempts('otp:lock:'.$phone, 1)) {
            $wait = RateLimiter::availableIn('otp:lock:'.$phone);

            return $this->tooMany("Too many wrong codes. Try again in {$wait} seconds.", $wait);
        }
        if (Turnstile::enabled() && ! Turnstile::verify($request->input('turnstileToken') ?: $request->header('X-Turnstile-Token'), $ip)) {
            return response()->json(['message' => 'Please complete the security check.', 'code' => 'challenge',
                'turnstileSiteKey' => config('security.turnstile.site_key')], 403);
        }
        if ($phone) {
            $key = 'otp:cooldown:'.$phone;
            if (RateLimiter::tooManyAttempts($key, 1)) {
                $wait = RateLimiter::availableIn($key);

                return $this->tooMany("Please wait {$wait} seconds before requesting another code.", $wait);
            }
        }
        $response = $next($request);
        if ($phone && $response->getStatusCode() < 300) {
            RateLimiter::hit('otp:cooldown:'.$phone, (int) config('security.otp.cooldown', 60));
        }

        return $response;
    }

    private function otpVerify(Request $request, Closure $next, string $ip): Response
    {
        $phone = Phone::normalize((string) $request->input('phone'));
        $lock = $phone ? 'otp:lock:'.$phone : null;
        if ($lock && RateLimiter::tooManyAttempts($lock, 1)) {
            $wait = RateLimiter::availableIn($lock);

            return $this->tooMany("Too many wrong codes. Try again in {$wait} seconds.", $wait);
        }
        $ipKey = 'otp:verify:ip:'.$ip;
        if (RateLimiter::tooManyAttempts($ipKey, (int) config('security.otp.ip_verify_per_10min', 20))) {
            return $this->tooMany('Too many attempts from this network. Try again later.', RateLimiter::availableIn($ipKey));
        }
        RateLimiter::hit($ipKey, 600);

        $response = $next($request);

        if ($phone && $this->wrongCode($response)) {
            $wrongKey = 'otp:wrong:'.$phone;
            $n = RateLimiter::hit($wrongKey, 3600);
            if ($n >= Limits::int('accounts.otp_wrong_lockout')) { // System → Limits (default config security.otp.wrong_lockout)
                RateLimiter::clear($wrongKey);
                RateLimiter::hit($lock, (int) config('security.otp.lockout_minutes', 30) * 60);
                ActivityLogger::log('security.otp_lockout', null, [], 'OTP lockout for '.Phone::mask($phone), ['ip' => $ip]);
            }
        } elseif ($phone && $response->getStatusCode() === 200) {
            RateLimiter::clear('otp:wrong:'.$phone);
        }

        return $response;
    }

    private function wrongCode(Response $response): bool
    {
        if (! in_array($response->getStatusCode(), [422, 429], true)) {
            return false;
        }
        $body = json_decode((string) $response->getContent(), true);

        return is_array($body) && isset($body['errors']['code']);
    }

    private function tooMany(string $message, int $wait): Response
    {
        return response()->json(['message' => $message, 'code' => 'rate_limited', 'retryAfter' => $wait], 429)->header('Retry-After', (string) $wait);
    }
}
