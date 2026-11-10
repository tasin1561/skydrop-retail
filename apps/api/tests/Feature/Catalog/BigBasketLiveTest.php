<?php

namespace Tests\Feature\Catalog;

use App\Services\Catalog\CatalogService;
use App\Services\Catalog\ProductWriter;
use App\Services\Categories\CategoryTree;
use App\Sources\Exceptions\SourceException;
use App\Sources\OxylabsBigBasketSource;
use Database\Seeders\CategoryTreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** BigBasket through a faked Oxylabs, with real bigbasket.com pages (Nov 2026). */
class BigBasketLiveTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->seed(CategoryTreeSeeder::class);
        CategoryTree::flush();
        $this->goLive('bigbasket');
    }

    private function fixture(string $name): string
    {
        return (string) gzdecode((string) file_get_contents(__DIR__.'/../../Fixtures/'.$name));
    }

    /** @param array<string, string> $pages path regex => fixture (or "" for an empty mobile shell, "613" for a failed job) */
    private function fake(array $pages): void
    {
        $shell = '<html><head></head><body>'.str_repeat('x', 1500).'</body></html>';
        Http::fake([
            'realtime.oxylabs.io/*' => function (Request $request) use ($pages, $shell) {
                $b = $request->data();
                $key = (string) $b['url'].' '.($b['user_agent_type'] ?? 'desktop');
                foreach ($pages as $re => $file) {
                    if (preg_match($re, $key)) {
                        // Oxylabs answers 613 ("faulted after too many retries"); a fake can't use that code, 500 behaves the same.
                        return $file === '613' ? Http::response(['message' => 'Faulted after too many retries'], 500)
                            : Http::response($this->oxylabsRaw($file === '' ? $shell : $this->fixture($file)));
                    }
                }

                return Http::response($this->oxylabsRaw($shell));
            },
        ]);
    }

    public function test_search_uses_mobile_brand_and_category_pages(): void
    {
        $this->fake(['#/pb/haldiram/ mobile#' => 'bigbasket_mobile_brand_haldirams.html.gz']);

        $page = app(OxylabsBigBasketSource::class)->search('haldiram', 1, 'price_asc');
        $this->assertGreaterThan(80, count($page->items));
        $prices = array_column($page->items, 'price_inr');
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices, 'sorted cheapest first');
        Http::assertSent(fn (Request $q) => $q['url'] === 'https://www.bigbasket.com/pc/haldiram/' && $q['user_agent_type'] === 'mobile');
        Http::assertSent(fn (Request $q) => $q['url'] === 'https://www.bigbasket.com/pb/haldiram/' && ! isset($q['render']));
    }

    public function test_search_falls_back_to_the_top_level_category_and_keeps_matching_products(): void
    {
        $this->fake(['#/cl/foodgrains-oil-masala/ mobile#' => 'bigbasket_mobile_category_rice.html.gz']);

        $items = app(OxylabsBigBasketSource::class)->search('basmati rice')->items;
        $this->assertNotEmpty($items);
        $this->assertStringContainsStringIgnoringCase('basmati', $items[0]['title'], 'best match first');
        $this->assertTrue(collect($items)->every(fn ($r) => preg_match('/basmati|rice/i', $r['title'])));
        Http::assertSent(fn (Request $q) => $q['url'] === 'https://www.bigbasket.com/cl/foodgrains-oil-masala/');
    }

    public function test_search_uses_category_pages_google_lists_and_filters_them(): void
    {
        $rice = $this->fixture('bigbasket_mobile_category_rice.html.gz');
        Http::fake(['realtime.oxylabs.io/*' => function (Request $request) use ($rice) {
            $b = $request->data();
            if (($b['source'] ?? '') === 'google_search') {
                return Http::response(['results' => [['status_code' => 200, 'content' => ['results' => ['organic' => [
                    ['url' => 'https://www.bigbasket.com/ss/basmati/'],
                    ['url' => 'https://www.facebook.com/Bigbasketcom/'],
                    ['url' => 'https://www.bigbasket.com/pc/old-path/basmati/'],
                    ['url' => 'https://www.bigbasket.com/pc/foodgrains-oil-masala/rice-rice-products/'],
                ]]]]]]);
            }

            return str_contains((string) $b['url'], 'rice-rice-products')
                ? Http::response($this->oxylabsRaw($rice))
                : Http::response($this->oxylabsRaw('<html><body>'.str_repeat('x', 1500).'</body></html>'));
        }]);

        $items = app(OxylabsBigBasketSource::class)->search('basmati')->items;
        $this->assertNotEmpty($items);
        $this->assertTrue(collect($items)->every(fn ($r) => stripos($r['title'], 'basmati') !== false), 'only the matching products of a broad page');
        Http::assertSent(fn (Request $q) => ($q['source'] ?? '') === 'google_search' && $q['query'] === 'bigbasket basmati');
        Http::assertSent(fn (Request $q) => ($q['url'] ?? '') === 'https://www.bigbasket.com/pc/old-path/basmati/');
        Http::assertNotSent(fn (Request $q) => str_contains((string) ($q['url'] ?? ''), '/ss/'));
    }

    public function test_product_page_tablet_first_then_the_mobile_title_price_keeps_stored_details(): void
    {
        $blocked = false;
        $rice = $this->fixture('bigbasket_product_40110353_rice_packs.html.gz');
        $mobile = $this->fixture('bigbasket_mobile_product_10000148.html.gz');
        Http::fake(['realtime.oxylabs.io/*' => function (Request $request) use (&$blocked, $rice, $mobile) {
            $ua = $request->data()['user_agent_type'] ?? '';

            return match (true) {
                $ua === 'tablet' && ! $blocked => Http::response($this->oxylabsRaw($rice)),
                $ua === 'mobile' => Http::response($this->oxylabsRaw($mobile)),
                default => Http::response(['message' => 'Faulted after too many retries'], 500),
            };
        }]);
        $src = app(OxylabsBigBasketSource::class);

        $p = $src->product('bb40110353');
        $this->assertSame(515.0, (float) $p->data['price_inr']);
        $this->assertCount(3, $p->data['variants']);
        app(ProductWriter::class)->detailed($p);

        // Now only the mobile page comes back: live price from its title, everything else as stored.
        $blocked = true;
        $p = $src->product('bb40110353')->data;
        $this->assertSame(66.0, (float) $p['price_inr'], 'live price from the mobile title');
        $this->assertSame(745.0, (float) $p['mrp_inr'], 'stored MRP kept');
        $this->assertCount(3, $p['variants'], 'stored pack sizes kept');
        $this->assertCount(5, $p['images']);
        $this->assertSame('Stock is confirmed when we buy', $p['stock_text']);
        Http::assertSent(fn (Request $q) => ($q['user_agent_type'] ?? '') === 'tablet');
        Http::assertNotSent(fn (Request $q) => ! isset($q['user_agent_type']) && str_contains((string) $q['url'], '/pd/'));
    }

    public function test_product_page_that_never_comes_back_is_an_error_not_an_empty_product(): void
    {
        $this->fake(['#/pd/# ' => '613']);
        $this->expectException(SourceException::class);
        app(OxylabsBigBasketSource::class)->product('bb40075196');
    }

    public function test_deals_and_bestsellers_read_category_pages(): void
    {
        $this->fake(['#/cl/foodgrains-oil-masala/#' => 'bigbasket_mobile_category_rice.html.gz', '#/pc/rice#' => 'bigbasket_mobile_category_rice.html.gz']);
        config(['retail.offers.deal_urls.bigbasket' => ['/cl/foodgrains-oil-masala/']]);

        $deals = app(OxylabsBigBasketSource::class)->deals()->items;
        $this->assertNotEmpty($deals);
        $this->assertTrue(collect($deals)->every(fn ($r) => $r['price_inr'] <= 0.9 * $r['mrp_inr']));

        $this->assertCount(136, app(OxylabsBigBasketSource::class)->bestsellers('/pc/rice-rice-products/', 2)->items);
        Http::assertSent(fn (Request $q) => $q['url'] === 'https://www.bigbasket.com/pc/rice-rice-products/?page=2');

        $this->assertGreaterThan(0, app(CatalogService::class)->seedBestsellers('bigbasket'));
    }

    public function test_store_search_through_the_api(): void
    {
        $this->fake(['#/pb/haldiram/ mobile#' => 'bigbasket_mobile_brand_haldirams.html.gz']);
        config(['retail.prefetch_top' => 0]);
        $r = $this->getJson('/api/v1/search?q=haldiram&store=bigbasket&wait=1', ['User-Agent' => 'Mozilla/5.0'])->assertOk();
        $this->assertNotEmpty($r->json('items') ?? []);
    }
}
