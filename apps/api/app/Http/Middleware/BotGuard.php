<?php

namespace App\Http\Middleware;

use App\Services\Audit\ActivityLogger;
use App\Services\Security\BotDetector;
use App\Services\Security\ClientIdentity;
use App\Services\Security\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bot detection for catalog reads: automation agents, headless hints, missing Accept-Language, too many distinct
 * products per minute per IP, product id enumeration (404 bursts). Flagged clients get a Turnstile challenge
 * (403 code=challenge); solving it (POST /security/challenge) clears the IP for an hour.
 */
class BotGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.enforce') || $request->attributes->get('client') === 'internal') {
            return $next($request);
        }
        $ip = ClientIdentity::ip($request);
        if (Turnstile::passed($ip)) {
            return $next($request);
        }

        $reasons = $request->attributes->get('client') === 'app' ? [] : BotDetector::headerSignals($request);
        if ($flag = BotDetector::flagged($ip)) {
            $reasons[] = $flag;
        }
        $productId = $this->productId($request);
        if ($productId && BotDetector::trackProduct($ip, $productId) > (int) config('security.bot.distinct_products_per_minute', 40)) {
            $reasons[] = 'too many distinct products per minute';
            BotDetector::flag($ip, 'too many distinct products per minute');
        }
        if ($reasons) {
            return $this->challenge($request, $ip, $reasons);
        }

        $response = $next($request);

        if ($productId && $response->getStatusCode() === 404 && BotDetector::trackMiss($ip) > 15) {
            BotDetector::flag($ip, 'product id enumeration');
        }

        return $response;
    }

    private function productId(Request $request): ?string
    {
        $route = $request->route();
        if (! $route || ! str_starts_with($route->uri(), 'api/v1/products/{id}')) {
            return null;
        }

        return (string) $route->parameter('id');
    }

    /** @param  array<int, string>  $reasons */
    private function challenge(Request $request, string $ip, array $reasons): Response
    {
        if (! cache()->add('bot:logged:'.$ip, 1, 600)) {
            // logged recently
        } else {
            ActivityLogger::log('security.bot', null, [], 'Bot challenge for '.$ip.': '.implode(', ', $reasons), ['ip' => $ip, 'path' => $request->path()]);
        }

        return response()->json([
            'message' => 'Please confirm you are human.', 'code' => 'challenge',
            'turnstileSiteKey' => Turnstile::enabled() ? config('security.turnstile.site_key') : null,
        ], 403);
    }
}
