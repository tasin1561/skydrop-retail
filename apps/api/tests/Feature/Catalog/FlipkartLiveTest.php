<?php

namespace Tests\Feature\Catalog;

use App\Filament\Support\Admin;
use App\Models\ApiLog;
use App\Models\Product;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\UrlResolver;
use App\Services\Categories\CategoryTree;
use App\Sources\OxylabsFlipkartSource;
use Database\Seeders\CategoryTreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Flipkart through a faked Oxylabs, with real flipkart.com pages. */
class FlipkartLiveTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    private const SHOE = 'itm9e64270628c7b-shohahu3yxjtahyf';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->seed(CategoryTreeSeeder::class);
        CategoryTree::flush();
        $this->goLive('flipkart');
        config(['retail.variants.mode' => 'manual']);
    }

    private function fixture(string $name): string
    {
        return (string) gzdecode((string) file_get_contents(__DIR__.'/../../Fixtures/'.$name));
    }

    private function fakeFlipkart(): void
    {
        $shoe = $this->fixture('flipkart_product_shoe_SHOHAHU3YXJTAHYF.html.gz');
        $grid = $this->fixture('flipkart_search_running_shoes_grid.html.gz');
        $list = $this->fixture('flipkart_search_smart_tv_list.html.gz');
        Http::fake([
            'realtime.oxylabs.io/*' => function (Request $request) use ($shoe, $grid, $list) {
                $b = $request->data();
                $url = (string) ($b['url'] ?? '');

                return match (true) {
                    ($b['source'] ?? '') === 'flipkart_search' => Http::response($this->oxylabsRaw($grid)),
                    str_contains($url, '/search?') || str_contains($url, '/pr?') => Http::response($this->oxylabsRaw($list)),
                    str_contains($url, '/p/itm') => Http::response($this->oxylabsRaw($shoe)),
                    default => Http::response(['message' => 'unexpected'], 400),
                };
            },
        ]);
    }

    public function test_product_page_is_fetched_without_rendering_for_the_exact_variant_and_stays_buyable(): void
    {
        $this->fakeFlipkart();

        $pending = $this->getJson('/api/v1/products/'.self::SHOE, ['User-Agent' => 'Mozilla/5.0'])->assertStatus(202)->assertJsonPath('market', 'flipkart');
        $this->getJson('/api/v1/jobs/'.$pending->json('jobId'))->assertOk()->assertJsonPath('status', 'done');
        $r = $this->getJson('/api/v1/products/'.self::SHOE, ['User-Agent' => 'Mozilla/5.0'])->assertOk()->assertJsonPath('status', 'ready');

        $this->assertSame(5199.0, (float) $r->json('product.priceInr'));
        $this->assertSame('Asics', $r->json('product.brand'));
        $trail = $r->json('product.categoryTrail');
        $this->assertNotEmpty($trail, 'breadcrumb uses our category tree');
        $this->assertMatchesRegularExpression('/shoe|footwear/i', end($trail)['name']);
        $this->assertNotEmpty($r->json('product.videos'));
        // Out-of-stock sizes come straight from the page (no extra call needed to know).
        $variants = collect($r->json('product.variants'))->keyBy('id');
        $this->assertFalse($variants['itm4f95c07cc77cd-shoh389bh8p3thpm']['available']);
        $this->assertTrue($variants['itm117e2ef2b6e9f-shohgdndmmng5qap']['available']);

        $p = Product::query()->findOrFail(self::SHOE);
        $this->assertFalse((bool) $p->hidden, '"Not deliverable at your location" (no pincode) must not hide Flipkart products');
        $this->assertTrue((bool) $p->in_stock);

        Http::assertSent(fn (Request $q) => $q['source'] === 'flipkart'
            && $q['url'] === 'https://www.flipkart.com/-/p/itm9e64270628c7b?pid=SHOHAHU3YXJTAHYF' && ! isset($q['render']));
        $this->assertEqualsWithDelta(0.0009, (float) ApiLog::query()->where('market', 'flipkart')->value('cost_usd'), 1e-9, 'unrendered rate');
    }

    public function test_checkout_price_check_passes_for_a_flipkart_product(): void
    {
        $this->fakeFlipkart();
        $this->getJson('/api/v1/products/'.self::SHOE.'?wait=1', ['User-Agent' => 'Mozilla/5.0'])->assertOk();

        $r = app(CatalogService::class)->verify([['productId' => self::SHOE, 'qty' => 1]]);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertTrue($r['results'][0]['ok']);
        $this->assertTrue($r['results'][0]['deliverable']);
        $this->assertSame('oxylabs', $r['results'][0]['provider']);
    }

    public function test_variant_prefetch_skips_out_of_stock_siblings_and_runs_in_parallel(): void
    {
        $this->fakeFlipkart();
        $this->getJson('/api/v1/products/'.self::SHOE.'?wait=1', ['User-Agent' => 'Mozilla/5.0'])->assertOk();
        $plan = app(CatalogService::class)->variantPlan(Product::query()->findOrFail(self::SHOE));
        $ids = [...$plan['colours'], ...$plan['sizes']];

        $this->assertNotContains('itm4f95c07cc77cd-shoh389bh8p3thpm', $ids, 'size 5 is out of stock on Flipkart');
        $this->assertContains('itm117e2ef2b6e9f-shohgdndmmng5qap', $ids);
        $this->assertNotContains(self::SHOE, $ids);

        $out = app(OxylabsFlipkartSource::class)->products(array_slice($ids, 0, 3));
        $this->assertCount(3, $out);
    }

    public function test_search_sorting_uses_the_search_url_and_relevance_uses_the_search_source(): void
    {
        $this->fakeFlipkart();
        $src = app(OxylabsFlipkartSource::class);

        $relevance = $src->search('running shoes men');
        $this->assertCount(40, $relevance->items);
        $this->assertSame('itm9e64270628c7b-shohahu3yxjtahyf', $relevance->items[0]['id']);

        $cheap = $src->search('smart tv', 2, 'price_asc');
        $this->assertCount(24, $cheap->items);

        Http::assertSent(fn (Request $q) => ($q['source'] ?? '') === 'flipkart_search' && $q['query'] === 'running shoes men' && ! isset($q['render']));
        Http::assertSent(fn (Request $q) => ($q['url'] ?? '') === 'https://www.flipkart.com/search?q=smart+tv&sort=price_asc&page=2');
    }

    public function test_deals_rotate_through_discounted_categories_keeping_flipkart_filters(): void
    {
        $this->fakeFlipkart();
        config(['retail.offers.deal_urls.flipkart' => [
            'https://www.flipkart.com/mobiles/pr?sid=tyy,4io&p[]=facets.discount_range_v1%5B%5D%3D30%25+or+more&sort=popularity',
        ]]);

        $page = app(OxylabsFlipkartSource::class)->deals();
        $this->assertNotEmpty($page->items);
        $this->assertTrue(collect($page->items)->every(fn ($r) => $r['price_inr'] <= 0.9 * $r['mrp_inr']), 'only real discounts');
        Http::assertSent(fn (Request $q) => ($q['url'] ?? '')
            === 'https://www.flipkart.com/mobiles/pr?sid=tyy,4io&p[]=facets.discount_range_v1%5B%5D%3D30%25+or+more&sort=popularity');

        $best = app(OxylabsFlipkartSource::class)->bestsellers('/televisions/pr?sid=ckf,czl', 2);
        $this->assertCount(24, $best->items);
        Http::assertSent(fn (Request $q) => ($q['url'] ?? '') === 'https://www.flipkart.com/televisions/pr?sid=ckf,czl&sort=popularity&page=2');
    }

    public function test_pasted_links_keep_the_variant(): void
    {
        $r = app(UrlResolver::class)->resolve('https://www.flipkart.com/asics-gt-2000/p/itm9e64270628c7b?pid=SHOHAHU3YXJTAHYF&lid=LST1&marketplace=FLIPKART');
        $this->assertSame(['market' => 'flipkart', 'id' => self::SHOE], array_intersect_key((array) $r, ['market' => 1, 'id' => 1]));
        Http::fake();
        $share = app(UrlResolver::class)->resolve('https://dl.flipkart.com/dl/-/p/itm9e64270628c7b?pid=SHOHAHU3YXJTAHYF&lid=LST1&_refId=x');
        $this->assertSame(self::SHOE, $share['id'] ?? null);
        Http::assertNothingSent();
    }

    public function test_admin_purchase_links_open_the_exact_store_page(): void
    {
        $this->assertSame('https://www.flipkart.com/-/p/itm9e64270628c7b?pid=SHOHAHU3YXJTAHYF', Admin::sourceUrl('flipkart', self::SHOE));
        $this->assertSame('https://www.flipkart.com/-/p/itm9e64270628c7b', Admin::sourceUrl('flipkart', 'itm9e64270628c7b'));
    }
}
