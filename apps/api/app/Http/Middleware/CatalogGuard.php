<?php

namespace App\Http\Middleware;

use App\Services\Security\AppSignature;
use App\Services\Security\Blocklist;
use App\Services\Security\ClientIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catalog endpoints answer only (a) the Next.js server (X-Internal-Key) or (b) signed app clients (X-App-Key + HMAC).
 * Also enforces pagination caps and the IP blocklist. Part of the "catalog.guard" middleware group.
 */
class CatalogGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.enforce')) {
            return $next($request);
        }
        $ip = ClientIdentity::ip($request);
        if (Blocklist::isBlocked('ip', $ip)) {
            return response()->json(['message' => 'Access denied.', 'code' => 'blocked'], 403);
        }

        $client = match (true) {
            AppSignature::isInternal($request) => 'internal',
            AppSignature::verify($request) => 'app',
            default => null,
        };
        if (! $client) {
            return response()->json(['message' => 'This endpoint needs a signed client.', 'code' => 'unsigned'], 401);
        }
        $request->attributes->set('client', $client);
        if ($client === 'app' && Blocklist::isBlocked('device', (string) $request->header('X-Device-Id'))) {
            return response()->json(['message' => 'Access denied.', 'code' => 'blocked'], 403);
        }

        $this->capPagination($request, $client);

        return $next($request);
    }

    private function capPagination(Request $request, string $client): void
    {
        $caps = config('security.pagination');
        $q = $request->query->all();
        if (isset($q['page']) && (int) $q['page'] > $caps['max_page']) {
            abort(response()->json(['message' => 'Page limit reached. Refine your search.', 'code' => 'page_limit'], 422));
        }
        foreach (['perPage', 'per_page', 'limit'] as $k) {
            if (isset($q[$k])) {
                $q[$k] = (string) max(1, min((int) $q[$k], $caps['max_per_page']));
            }
        }
        // No bulk id listing: a handful of ids for wishlists/recently viewed only.
        if (isset($q['ids'])) {
            $max = $client === 'internal' ? 100 : $caps['max_ids'];
            $q['ids'] = implode(',', array_slice(array_filter(explode(',', (string) $q['ids'])), 0, $max));
        }
        $request->query->replace($q);
    }
}
