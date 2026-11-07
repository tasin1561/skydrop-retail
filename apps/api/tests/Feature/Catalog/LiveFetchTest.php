<?php

namespace Tests\Feature\Catalog;

use App\Models\ApiLog;
use App\Models\FetchJob;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProviderHealth;
use App\Models\RawPage;
use App\Models\SearchCache;
use App\Services\Catalog\BudgetGuard;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\ProviderChain;
use App\Services\Catalog\UrlResolver;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** live_fetch=true paths with a faked Oxylabs (queue is sync in tests, so jobs run inline). */
class LiveFetchTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->goLive('amazon');
        config(['retail.variants.mode' => 'manual']);
    }

    public function test_unknown_product_is_fetched_raw_with_pincode_and_parsed(): void
    {
        $this->fakeOxylabs();
        Product::query()->whereKey('B0FMDLD86P')->delete();

        $r = $this->getJson('/api/v1/products/B0FMDLD86P', ['User-Agent' => 'Mozilla/5.0'])->assertOk()->assertJsonPath('status', 'ready');
        $this->assertSame(1849.0, (float) $r->json('product.priceInr'));
        $this->assertSame('Mega Festival Deal', $r->json('product.dealBadge'));
        $this->assertCount(4, $r->json('product.videos'));
        $this->assertTrue($r->json('product.pincodeVerified'));
        $this->assertSame('electronics', $r->json('product.category'));

        Http::assertSent(fn (Request $req) => $req['source'] === 'amazon_product' && $req['geo_location'] === '700001'
            && $req['domain'] === 'in' && ! isset($req['parse']) && $req->hasHeader('Authorization', 'Bearer test-token'));
        $log = ApiLog::query()->where('provider', 'oxylabs')->first();
        $this->assertSame('product', $log->type);
        $this->assertEqualsWithDelta(0.00045, (float) $log->cost_usd, 1e-9);
        $this->assertSame(1, RawPage::query()->count());
        $this->assertSame(1849.0, (float) PriceHistory::query()->where('product_id', 'B0FMDLD86P')->value('price_inr'));
    }

    public function test_stale_product_returns_cached_data_and_queues_refresh(): void
    {
        $this->fakeOxylabs();
        Product::query()->whereKey('B0FMDLD86P')->update(['details_fetched_at' => now()->subDays(2), 'price_inr' => 1999]);

        $r = $this->getJson('/api/v1/products/B0FMDLD86P')->assertOk()->assertJsonPath('status', 'pending');
        $this->assertSame(1999.0, (float) $r->json('product.priceInr'), 'stale data served immediately');
        $job = FetchJob::query()->find($r->json('jobId'));
        $this->assertSame('done', $job->status);
        $this->getJson('/api/v1/jobs/'.$job->id)->assertOk()->assertJsonPath('status', 'done')->assertJsonPath('type', 'product');
        $this->assertSame(1849.0, Product::query()->find('B0FMDLD86P')->price_inr);
        $this->assertSame(1, PriceHistory::query()->where('product_id', 'B0FMDLD86P')->count());

        // Fresh now: a second view does not call the provider again and logs a cache hit.
        Http::fake(fn () => throw new \RuntimeException('should not be called'));
        $this->getJson('/api/v1/products/B0FMDLD86P')->assertJsonPath('status', 'ready');
        $this->assertTrue(ApiLog::query()->where('type', 'cache_hit')->exists());
    }

    public function test_expired_deal_forces_refresh_on_view(): void
    {
        $this->fakeOxylabs();
        Product::query()->whereKey('B0FMDLD86P')->update(['details_fetched_at' => now(), 'deal_badge' => 'Lightning Deal', 'deal_ends_at' => now()->subMinute()]);
        $this->getJson('/api/v1/products/B0FMDLD86P')->assertJsonPath('status', 'pending');
    }

    public function test_crawlers_never_trigger_fetches(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Product::query()->whereKey('B0FMDLD86P')->update(['details_fetched_at' => now()->subDays(2)]);
        $this->getJson('/api/v1/products/B0FMDLD86P', ['User-Agent' => 'Googlebot/2.1'])->assertJsonPath('status', 'ready');
        $this->getJson('/api/v1/products/B0ZZZZZZZZ', ['User-Agent' => 'facebookexternalhit/1.1'])->assertNotFound();
        $this->getJson('/api/v1/search?q=earbuds', ['User-Agent' => 'bingbot'])->assertJsonPath('status', 'ready');
        Http::assertNothingSent();
    }

    public function test_search_miss_queues_store_page_and_upserts_listings(): void
    {
        $this->fakeOxylabs();
        config(['retail.prefetch_top' => 0]);
        $r = $this->getJson('/api/v1/search?q=bluetooth earbuds')->assertOk()->assertJsonPath('status', 'pending');
        $this->assertNotNull($r->json('jobId'));
        $this->assertSame('done', FetchJob::query()->find($r->json('jobId'))->status);
        $cache = SearchCache::query()->sole();
        $this->assertSame('amazon|bluetooth earbuds|1|relevance', $cache->cache_key);
        $this->assertGreaterThan(10, count(json_decode($cache->product_ids, true)));
        $coupon = Product::query()->find('B0HHNTFNGR');
        $this->assertSame(281.3, $coupon->coupon_inr);

        // Second request: cached, ready, store results included even when words do not all match.
        $again = $this->getJson('/api/v1/search?q=bluetooth earbuds')->assertJsonPath('status', 'ready');
        $this->assertContains('B0HHNTFNGR', array_column($again->json('items'), 'id'));
        Http::assertSentCount(1);
    }

    public function test_circuit_breaker_opens_after_repeated_failures(): void
    {
        config(['retail.circuit.failures' => 3]);
        $down = true;
        $html = $this->amazonHtml();
        Http::fake(['realtime.oxylabs.io/*' => function () use (&$down, $html) {
            return $down ? Http::response('boom', 500) : Http::response($this->oxylabsRaw($html));
        }]);
        $chain = app(ProviderChain::class);
        for ($i = 0; $i < 3; $i++) {
            $this->assertNull($chain->product('amazon', 'B0FMDLD86P'));
        }
        $this->assertSame('open', ProviderHealth::query()->find('oxylabs:amazon')->state);
        $this->assertNull($chain->product('amazon', 'B0FMDLD86P'));
        Http::assertSentCount(3);

        // After open_for, one half-open probe goes through and closes the circuit on success.
        ProviderHealth::query()->whereKey('oxylabs:amazon')->update(['opened_at' => now()->subHour()]);
        $down = false;
        $this->assertNotNull($chain->product('amazon', 'B0FMDLD86P'));
        $this->assertSame('closed', ProviderHealth::query()->find('oxylabs:amazon')->state);
    }

    public function test_fallback_provider_used_when_oxylabs_fails_and_marked_unverified(): void
    {
        config(['retail.fallbacks.openwebninja.key' => 'rapid']);
        Http::fake([
            'realtime.oxylabs.io/*' => Http::response('down', 503),
            'real-time-amazon-data.p.rapidapi.com/*' => Http::response(['data' => [
                'product_title' => 'OnePlus Nord Buds 3r', 'product_price' => '₹1,799', 'product_original_price' => '₹1,999',
                'product_star_rating' => '4.3', 'product_num_ratings' => 52000, 'product_photos' => ['https://m.media-amazon.com/a.jpg'],
                'product_availability' => 'In stock', 'about_product' => ['Long battery'],
            ]]),
        ]);
        $p = app(CatalogService::class)->refreshProduct('B0FMDLD86P');
        $this->assertSame(1799.0, $p->price_inr);
        $this->assertFalse($p->pincode_verified);
        $this->assertSame('openwebninja', $p->source);
    }

    public function test_budget_exhausted_reserves_oxylabs_for_checkout(): void
    {
        $this->fakeOxylabs();
        config(['retail.oxylabs.monthly_budget_usd' => 1]);
        ApiLog::query()->create(['provider' => 'oxylabs', 'type' => 'product', 'status' => 200, 'ms' => 1, 'cost_usd' => 2]);
        $this->assertFalse(app(BudgetGuard::class)->allowsMetered('browse'));

        $this->assertNull(app(ProviderChain::class)->product('amazon', 'B0FMDLD86P'));
        Http::assertNothingSent();

        $v = app(CatalogService::class)->verify([['productId' => 'B0FMDLD86P', 'qty' => 1, 'unitBdt' => 1]]);
        $this->assertTrue($v['results'][0]['ok']);
        Http::assertSentCount(1);
    }

    public function test_verify_reports_price_change_and_expired_coupon(): void
    {
        $this->fakeOxylabs();
        $p = Product::query()->find('B0FMDLD86P');
        $p->update(['price_inr' => 1700, 'coupon_inr' => 100, 'effective_price_inr' => 1600]);
        $catalog = app(CatalogService::class);

        $r = $catalog->verify([['productId' => 'B0FMDLD86P', 'qty' => 1, 'unitBdt' => 3000]])['results'][0];
        $this->assertTrue($r['ok']);
        $this->assertTrue($r['changed']);
        $this->assertTrue($r['offerExpired'], 'coupon gone on the live page');
        $this->assertSame(app(PricingService::class)->priceBdt(1849, 'electronics'), $r['freshBdt']);
        $this->assertSame('oxylabs', $r['provider']);
    }

    public function test_out_of_stock_page_hides_the_product_but_keeps_it(): void
    {
        $html = str_replace(['In stock', 'id="twister-plus-price-data-price" value="1849"'], ['Currently unavailable.', 'id="twister-plus-price-data-price" value="0"'], $this->amazonHtml());
        $this->fakeOxylabs($html);
        $p = app(CatalogService::class)->refreshProduct('B0FMDLD86P');
        $this->assertTrue($p->hidden);
        $this->assertFalse($p->in_stock);
        $this->assertGreaterThan(0, $p->price_inr, 'last known price kept');
        $this->getJson('/api/v1/products/B0FMDLD86P')->assertOk();
        $this->assertNotContains('B0FMDLD86P', array_column($this->getJson('/api/v1/search?q=oneplus')->json('items'), 'id'));
    }

    public function test_variants_fetch_colours_first_then_sizes_and_bounce_cancel(): void
    {
        $this->fakeOxylabs();
        config(['retail.variants.mode' => 'eager']);
        Product::query()->whereIn('id', ['B0C8JB3G5W', 'B0GTFQG1BQ', 'B0FMDL81GS', 'B0C8J7W6PL'])->delete();
        $catalog = app(CatalogService::class);
        $plan = $catalog->variantPlan(Product::query()->with('details')->find('B0FMDLD86P'));
        $this->assertCount(4, $plan['colours']);
        $this->assertSame([], $plan['sizes']);

        $jobId = $catalog->queueVariants('B0FMDLD86P');
        $this->assertSame('done', FetchJob::query()->find($jobId)->status);
        $this->assertSame(4, Product::query()->whereIn('id', $plan['colours'])->where('detailed', true)->count());

        // Cancelling marks the size phase as dropped.
        $catalog->cancelVariants('B0FMDLD86P');
        $this->assertTrue($catalog->variantsCancelled('B0FMDLD86P'));
    }

    public function test_resolve_unknown_link_queues_fetch(): void
    {
        $this->fakeOxylabs();
        $r = $this->postJson('/api/v1/products/resolve', ['url' => 'amazon.in/gp/product/B0ABCDEFGH'])->assertStatus(202)
            ->assertJsonPath('status', 'pending')->assertJsonPath('productId', 'B0ABCDEFGH');
        $this->assertNotNull($r->json('jobId'));
        $this->assertTrue(Product::query()->whereKey('B0ABCDEFGH')->exists());
    }

    public function test_short_links_are_followed(): void
    {
        Http::fake(['amzn.in/*' => Http::response('', 301, ['Location' => 'https://www.amazon.in/dp/B0FMDLD86P?ref=x'])]);
        $this->assertSame(['market' => 'amazon', 'id' => 'B0FMDLD86P'], app(UrlResolver::class)->resolve('https://amzn.in/d/abc123'));
        $this->assertSame(['market' => 'bigbasket', 'id' => 'bb40001234'], app(UrlResolver::class)->resolve('https://www.bigbasket.com/pd/40001234/india-gate-rice/'));
    }

    public function test_hedged_request_returns_first_success(): void
    {
        config(['retail.oxylabs.hedge_after' => 1]);
        $this->fakeOxylabs();
        $p = app(ProviderChain::class)->product('amazon', 'B0FMDLD86P', 'browse', interactive: true);
        $this->assertSame(1849.0, $p->data['price_inr']);
    }

    public function test_budget_alerts_fire_once_per_level(): void
    {
        config(['retail.oxylabs.monthly_budget_usd' => 10]);
        ApiLog::query()->create(['provider' => 'oxylabs', 'type' => 'product', 'status' => 200, 'ms' => 1, 'cost_usd' => 7.5]);
        $guard = app(BudgetGuard::class);
        $this->assertSame(70, $guard->check()['alerted']);
        $this->assertNull($guard->check()['alerted']);
        $this->artisan('retail:budget')->assertSuccessful();
    }
}
