<?php

namespace App\Http\Controllers\Api\Tracking;

use App\Http\Controllers\Controller;
use App\Services\Tracking\ProductFeed;
use App\Services\Tracking\TrackingSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** GET /feeds/meta.csv and /feeds/google.xml (?store=amazon|flipkart|bigbasket, ?key= when a feed key is set). */
class FeedController extends Controller
{
    public const STORES = ['amazon', 'flipkart', 'bigbasket'];

    public function meta(Request $request, ProductFeed $feed): Response
    {
        $store = $this->guard($request);
        $body = Cache::remember('feed:meta:'.($store ?? 'all'), $this->ttl(), fn () => $feed->csv($store));

        return response($body, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function google(Request $request, ProductFeed $feed): Response
    {
        $store = $this->guard($request);
        $body = Cache::remember('feed:google:'.($store ?? 'all'), $this->ttl(), fn () => $feed->googleXml($store));

        return response($body, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    private function guard(Request $request): ?string
    {
        $key = TrackingSettings::str('feed_key');
        if ($key !== '' && ! hash_equals($key, (string) $request->query('key', ''))) {
            abort(403, 'Invalid feed key.');
        }
        $store = $request->query('store');
        if ($store === null || $store === '' || $store === 'all') {
            return null;
        }
        abort_unless(in_array($store, self::STORES, true), 422, 'Unknown store.');

        return $store;
    }

    private function ttl(): int
    {
        return 60 * (int) config('retail.tracking.feed_cache_minutes', 60);
    }
}
