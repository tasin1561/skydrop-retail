<?php

namespace App\Http\Middleware;

use App\Services\Audit\ActivityLogger;
use App\Services\Security\AppSignature;
use App\Services\Security\BotDetector;
use App\Services\Security\ClientIdentity;
use App\Services\Security\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anti-scraping for the public cart endpoints the browser calls directly (POST checkout/quote, checkout/alternatives).
 * They return server prices for any product ids, so without this they are a catalog price oracle outside catalog.guard.
 * Counts distinct product ids per IP per minute (BotDetector, bucket "cart"); above the limit the IP is flagged and gets
 * the same Turnstile challenge as BotGuard (403 code=challenge). The Next.js server and signed apps are not counted here.
 */
class CartLookupGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.enforce') || AppSignature::isInternal($request) || AppSignature::verify($request)) {
            return $next($request);
        }
        $ip = ClientIdentity::ip($request);
        if (Turnstile::passed($ip)) {
            return $next($request);
        }
        $reason = BotDetector::flagged($ip);
        if (! $reason) {
            $ids = array_slice(array_unique(array_filter(array_map(
                fn ($i) => is_array($i) && is_scalar($i['productId'] ?? null) ? mb_substr((string) $i['productId'], 0, 40) : null,
                (array) $request->input('items', []),
            ))), 0, 200);
            $max = (int) config('security.bot.cart_products_per_minute', 120);
            $seen = 0;
            foreach ($ids as $id) {
                $seen = BotDetector::trackProduct($ip, $id, 'cart');
            }
            if ($seen > $max) {
                $reason = 'too many distinct products in cart lookups';
                BotDetector::flag($ip, $reason);
            }
        }
        if ($reason) {
            if (cache()->add('bot:logged:'.$ip, 1, 600)) {
                ActivityLogger::log('security.bot', null, [], 'Bot challenge for '.$ip.': '.$reason, ['ip' => $ip, 'path' => $request->path()]);
            }

            return response()->json([
                'message' => 'Please confirm you are human.', 'code' => 'challenge',
                'turnstileSiteKey' => Turnstile::enabled() ? config('security.turnstile.site_key') : null,
            ], 403);
        }

        return $next($request);
    }
}
