<?php

namespace Tests\Feature\Catalog;

use App\Models\Marketplace;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Catalog\CatalogService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Offline behaviour (live_fetch=false): every endpoint is served from the local catalog, no provider calls. */
class CatalogApiTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        Http::preventStrayRequests();
    }

    public function test_marketplaces(): void
    {
        $this->getJson('/api/v1/marketplaces')->assertOk()
            ->assertJsonPath('items.0', ['id' => 'amazon', 'name' => 'Amazon', 'source' => 'amazon.in', 'enabled' => true, 'liveFetch' => false])
            ->assertJsonCount(3, 'items');
    }

    public function test_search_scores_filters_and_sorts_like_the_web_catalog(): void
    {
        $r = $this->getJson('/api/v1/search?q=juice&sort=price_asc')->assertOk()->assertJsonPath('status', 'ready');
        $items = $r->json('items');
        $this->assertSame(57, $r->json('total'));
        $this->assertSame(3, $r->json('pages'));
        $this->assertCount(24, $items);
        $prices = array_column($items, 'priceInr');
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices);
        foreach ($items as $p) {
            $this->assertStringContainsString('juice', strtolower($p['title'].' '.$p['brand'].' '.$p['category']));
        }

        // Relevance: title starting with the word scores 3, else 1; ties by review count.
        $one = $this->getJson('/api/v1/search?q=one buds')->json('items');
        $this->assertStringStartsWith('OnePlus', $one[0]['title']);

        $flip = $this->getJson('/api/v1/search?store=flipkart')->json('items');
        $this->assertTrue(collect($flip)->every(fn ($p) => $p['market'] === 'flipkart'));

        $bdt = $this->getJson('/api/v1/search?min=500&max=3000')->json('items');
        $this->assertTrue(collect($bdt)->every(fn ($p) => $p['priceBdt'] >= 500 && $p['priceBdt'] <= 3000));

        $this->getJson('/api/v1/search?sort=bogus')->assertStatus(422);
    }

    public function test_disabled_marketplace_is_excluded(): void
    {
        Marketplace::query()->whereKey('flipkart')->update(['enabled' => false]);
        $this->assertSame(0, $this->getJson('/api/v1/search?store=flipkart')->json('total'));
        $this->assertTrue(collect($this->getJson('/api/v1/search?q=shirt')->json('items'))->every(fn ($p) => $p['market'] !== 'flipkart'));
    }

    public function test_suggest(): void
    {
        $items = $this->getJson('/api/v1/suggest?q=oneplus&store=amazon')->assertOk()->json('items');
        $this->assertNotEmpty($items);
        $this->assertSame(['id', 'title', 'market'], array_keys($items[0]));
        $this->assertLessThanOrEqual(6, count($items));
    }

    public function test_product_detail_shape_matches_web_type(): void
    {
        $r = $this->getJson('/api/v1/products/B0FMDLD86P')->assertOk()->assertJsonPath('status', 'ready');
        $p = $r->json('product');
        foreach (['id', 'market', 'category', 'title', 'brand', 'priceInr', 'mrpInr', 'priceBdt', 'mrpBdt', 'rating', 'reviews', 'image', 'images',
            'prime', 'bestSeller', 'amazonsChoice', 'salesVolume', 'detailed', 'stock', 'bullets', 'specs', 'weight', 'variants', 'aplus',
            'hasVideos', 'videos', 'reviewList', 'starBreakdown', 'deliveryKolkata', 'categoryPath', 'fetchedAt', 'pincodeVerified',
            'offers', 'dealBadge', 'couponInr', 'effectivePriceInr', 'dealEndsAt'] as $k) {
            $this->assertArrayHasKey($k, $p, "missing $k");
        }
        $this->assertSame(app(PricingService::class)->productBdt(Product::query()->find('B0FMDLD86P')), $p['priceBdt']);
        $this->assertCount(5, $p['variants']);
        $this->assertSame(['id', 'label', 'dimensions', 'swatch', 'selected', 'available'], array_keys($p['variants'][0]));
        $this->assertCount(4, $p['videos']);
        $this->assertTrue($p['pincodeVerified']);

        $this->getJson('/api/v1/products/NOPE000000')->assertNotFound();
    }

    public function test_variant_availability_reflects_known_siblings(): void
    {
        Product::query()->whereKey('B0FMDL81GS')->update(['in_stock' => false, 'hidden' => true]);
        $variants = collect($this->getJson('/api/v1/products/B0FMDLD86P')->json('product.variants'))->keyBy('id');
        $this->assertFalse($variants['B0FMDL81GS']['available']);
        $this->assertTrue($variants['B0GTFQG1BQ']['available'], 'unknown siblings default to available');
        $this->assertTrue($variants['B0FMDLD86P']['available']);
    }

    public function test_cards_by_ids_popular_deals_related(): void
    {
        $this->assertSame(['B07QQ71X3Q', 'B0FMDLD86P'], array_column($this->getJson('/api/v1/products?ids=B07QQ71X3Q,missing,B0FMDLD86P')->json('items'), 'id'));

        $popular = $this->getJson('/api/v1/products/popular?store=amazon&category=electronics&limit=5')->json('items');
        $this->assertCount(5, $popular);
        $reviews = array_column($popular, 'reviews');
        $this->assertSame($reviews, collect($reviews)->sortDesc()->values()->all());

        $deals = $this->getJson('/api/v1/products/deals?store=amazon&limit=4')->json('items');
        $this->assertCount(4, $deals);
        $this->assertGreaterThan($deals[0]['priceInr'], $deals[0]['mrpInr']);
        $this->assertArrayHasKey('offers', $deals[0]);

        $related = $this->getJson('/api/v1/products/B0FMDLD86P/related?limit=3')->json('items');
        $this->assertCount(3, $related);
        $this->assertTrue(collect($related)->every(fn ($p) => $p['category'] === 'electronics' && $p['id'] !== 'B0FMDLD86P'));
    }

    public function test_offers_endpoint_lists_live_coupons_and_deals_by_saving(): void
    {
        Product::query()->whereKey('B07QQ71X3Q')->update(['coupon_inr' => 10, 'effective_price_inr' => 65, 'saving_inr' => 85]);
        Product::query()->whereKey('B0FMDLD86P')->update(['deal_badge' => 'Limited time deal', 'saving_inr' => 150, 'deal_ends_at' => now()->addHour()]);
        Product::query()->whereKey('B0FMDL81GS')->update(['deal_badge' => 'Lightning Deal', 'saving_inr' => 999, 'deal_ends_at' => now()->subMinute()]);

        $items = $this->getJson('/api/v1/offers?store=amazon')->assertOk()->json('items');
        $this->assertSame(['B0FMDLD86P', 'B07QQ71X3Q'], array_column($items, 'id'), 'expired deals are excluded');
        $this->assertSame(10.0, (float) $items[1]['couponInr']);
        // Coupon passes through to the BDT price (pricing uses effectivePriceInr).
        $this->assertSame(app(PricingService::class)->priceBdt(65, 'grocery'), $items[1]['priceBdt']);

        $this->assertSame(['B07QQ71X3Q'], array_column($this->getJson('/api/v1/offers?type=coupon')->json('items'), 'id'));
        $this->getJson('/api/v1/offers?type=nonsense')->assertStatus(422);
    }

    public function test_pass_coupons_setting_turns_coupon_pricing_off(): void
    {
        Product::query()->whereKey('B07QQ71X3Q')->update(['coupon_inr' => 10, 'effective_price_inr' => 65]);
        Setting::put('pass_coupons', false);
        Cache::flush();
        $this->assertSame(app(PricingService::class)->priceBdt(75, 'grocery'), $this->getJson('/api/v1/products/B07QQ71X3Q')->json('product.priceBdt'));
    }

    public function test_jobs_and_cancel_variants(): void
    {
        $this->getJson('/api/v1/jobs/00000000-0000-0000-0000-000000000000')->assertNotFound();
        $this->postJson('/api/v1/products/B0FMDLD86P/cancel-variants')->assertOk()->assertJsonPath('ok', true);
    }

    public function test_verify_against_db_when_offline(): void
    {
        $catalog = app(CatalogService::class);
        $p = Product::query()->find('B07QQ71X3Q');
        $bdt = app(PricingService::class)->productBdt($p);
        $r = $catalog->verify([
            ['productId' => 'B07QQ71X3Q', 'qty' => 1, 'unitBdt' => $bdt],
            ['productId' => 'B0FMDLD86P', 'qty' => 1, 'unitBdt' => 1],
            ['productId' => 'GONE000000', 'qty' => 1, 'unitBdt' => 1],
        ]);
        $this->assertFalse($r['ok']);
        $this->assertTrue($r['results'][0]['ok']);
        $this->assertFalse($r['results'][0]['changed']);
        $this->assertTrue($r['results'][1]['changed']);
        $this->assertSame('not_found', $r['results'][2]['reason']);

        // An expired deal price is reported and no longer used.
        $p->update(['deal_badge' => 'Lightning Deal', 'effective_price_inr' => 60, 'deal_ends_at' => now()->subMinute()]);
        $v = $catalog->verify([['productId' => 'B07QQ71X3Q', 'qty' => 1, 'unitBdt' => $bdt]])['results'][0];
        $this->assertTrue($v['offerExpired']);
        $this->assertSame($bdt, $v['freshBdt']);
    }

    public function test_resolve_links(): void
    {
        $this->postJson('/api/v1/products/resolve', ['url' => 'https://www.amazon.in/OnePlus-Buds/dp/B0FMDLD86P/ref=sr_1_1?th=1'])
            ->assertOk()->assertJsonPath('status', 'ready')->assertJsonPath('productId', 'B0FMDLD86P')->assertJsonPath('product.id', 'B0FMDLD86P');
        $this->postJson('/api/v1/products/resolve', ['url' => 'https://www.ebay.com/itm/123'])
            ->assertStatus(422)->assertJsonPath('errors.url.0', 'Paste a product link from amazon.in, flipkart.com or myntra.com.');
        $this->postJson('/api/v1/products/resolve', ['url' => 'https://www.amazon.in/s?k=earbuds'])->assertStatus(422);
        // Not in the DB and the store is offline: friendly 422, no provider call.
        $this->postJson('/api/v1/products/resolve', ['url' => 'https://www.flipkart.com/x/p/itm61400c2ed6802?pid=SHT'])->assertStatus(422);
    }

    public function test_seeders_import_the_bundled_catalog(): void
    {
        $this->assertSame(267, Product::query()->count());
        $this->assertSame(3, Marketplace::query()->count());
        $this->assertSame(6, PricingRule::query()->count());
        $this->assertSame(0, Product::query()->where('market', 'bigbasket')->count());
    }
}
