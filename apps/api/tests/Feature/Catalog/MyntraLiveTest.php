<?php

namespace Tests\Feature\Catalog;

use App\Filament\Support\Admin;
use App\Models\ApiLog;
use App\Models\Product;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\MarketResolver;
use App\Services\Catalog\UrlResolver;
use App\Services\Categories\CategoryTree;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\CommerceException;
use App\Sources\OxylabsMyntraSource;
use Database\Seeders\CategoryTreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Myntra through a faked Oxylabs, with real myntra.com pages (Nov 2026). */
class MyntraLiveTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    private const TEE = 'my12027436';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->seed(CategoryTreeSeeder::class);
        CategoryTree::flush();
        $this->goLive('myntra');
        config(['retail.variants.mode' => 'manual']);
    }

    private function fixture(string $name): string
    {
        return (string) gzdecode((string) file_get_contents(__DIR__.'/../../Fixtures/'.$name));
    }

    private function fakeMyntra(): void
    {
        $tee = $this->fixture('myntra_product_12027436.html.gz');
        $list = $this->fixture('myntra_listing_men_tshirts.html.gz');
        $search = $this->fixture('myntra_search_running_shoes_price_asc_p2.html.gz');
        Http::fake([
            'realtime.oxylabs.io/*' => function (Request $request) use ($tee, $list, $search) {
                $b = $request->data();
                $url = (string) ($b['url'] ?? '');
                if (($b['source'] ?? '') !== 'universal') {
                    return Http::response(['message' => 'unexpected'], 400);
                }

                return match (true) {
                    (bool) preg_match('#^https://www\.myntra\.com/\d+$#', $url) => ($b['render'] ?? null) === 'html'
                        ? Http::response($this->oxylabsRaw($tee))
                        : Http::response(['message' => 'Faulted after too many retries'], 613),
                    str_contains($url, 'rawQuery=') => Http::response($this->oxylabsRaw($search)),
                    default => Http::response($this->oxylabsRaw($list)),
                };
            },
        ]);
    }

    public function test_product_page_is_rendered_and_sizes_are_options_of_the_same_product(): void
    {
        $this->fakeMyntra();

        $r = $this->getJson('/api/v1/products/'.self::TEE.'?wait=1', ['User-Agent' => 'Mozilla/5.0'])->assertOk()
            ->assertJsonPath('status', 'ready')->assertJsonPath('product.market', 'myntra');

        $this->assertSame(389.0, (float) $r->json('product.priceInr'));
        $this->assertSame('Levis', $r->json('product.brand'));
        $this->assertGreaterThan(5, count($r->json('product.images')));
        $this->assertNotEmpty($r->json('product.videos'));
        $trail = $r->json('product.categoryTrail');
        $this->assertNotEmpty($trail, 'breadcrumb uses our category tree');

        $variants = collect($r->json('product.variants'));
        $sizes = $variants->whereNull('id');
        $this->assertSame(['White / S', 'White / M', 'White / L', 'White / XL'], $sizes->pluck('label')->all());
        $this->assertTrue($sizes->first()['selected'], 'first size in stock is preselected');
        $this->assertTrue($sizes->every(fn ($v) => $v['available'] === true));
        $this->assertContains('my12027418', $variants->pluck('id')->all(), 'other colours are their own Myntra styles');

        $p = Product::query()->findOrFail(self::TEE);
        $this->assertSame('fashion', $p->category);
        $this->assertFalse((bool) $p->hidden);
        $this->assertTrue((bool) $p->in_stock);

        Http::assertSent(fn (Request $q) => $q['source'] === 'universal' && $q['url'] === 'https://www.myntra.com/12027436' && $q['render'] === 'html');
        $this->assertEqualsWithDelta(0.0014, (float) ApiLog::query()->where('market', 'myntra')->value('cost_usd'), 1e-9, 'rendered rate');
    }

    public function test_no_variant_prefetch_colours_load_when_tapped(): void
    {
        $this->fakeMyntra();
        $this->getJson('/api/v1/products/'.self::TEE.'?wait=1', ['User-Agent' => 'Mozilla/5.0'])->assertOk();
        $plan = app(CatalogService::class)->variantPlan(Product::query()->findOrFail(self::TEE));

        $this->assertSame(['colours' => [], 'sizes' => []], $plan, 'sizes are on this page; colours are fetched on demand');
        $this->assertSame(1, ApiLog::query()->where('market', 'myntra')->count());

        // Tapping another colour opens that style (its own product id).
        $this->getJson('/api/v1/products/my12027418?wait=1', ['User-Agent' => 'Mozilla/5.0'])->assertOk()->assertJsonPath('product.market', 'myntra');
    }

    public function test_checkout_price_check_passes_for_a_myntra_product(): void
    {
        $this->fakeMyntra();
        $this->getJson('/api/v1/products/'.self::TEE.'?wait=1', ['User-Agent' => 'Mozilla/5.0'])->assertOk();

        $r = app(CatalogService::class)->verify([['productId' => self::TEE, 'variant' => 'White / M', 'qty' => 1]]);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertTrue($r['results'][0]['deliverable']);
    }

    public function test_checkout_needs_a_size_that_is_in_stock(): void
    {
        $this->fakeMyntra();
        $this->getJson('/api/v1/products/'.self::TEE.'?wait=1', ['User-Agent' => 'Mozilla/5.0'])->assertOk();
        $d = Product::query()->findOrFail(self::TEE)->details;
        $d->variants = array_map(fn ($v) => $v['label'] === 'White / XL' ? ['in_stock' => false] + $v : $v, $d->variants);
        $d->save();
        $checkout = app(CheckoutService::class);

        $this->assertSame('White / M', $checkout->lines([['productId' => self::TEE, 'variant' => 'White / M', 'qty' => 1]])[0]['variant']);
        foreach ([null => 'Pick a size', 'White / XL' => 'sold out'] as $variant => $msg) {
            try {
                $checkout->lines([['productId' => self::TEE, 'variant' => $variant ?: null, 'qty' => 1]]);
                $this->fail('expected a size error');
            } catch (CommerceException $e) {
                $this->assertStringContainsString($msg, $e->getMessage());
            }
        }
    }

    public function test_search_listing_and_sorting_use_unrendered_listing_pages(): void
    {
        $this->fakeMyntra();
        $src = app(OxylabsMyntraSource::class);

        $cheap = $src->search('running shoes', 2, 'price_asc');
        $this->assertCount(50, $cheap->items);
        $this->assertSame('my30250153', $cheap->items[0]['id']);
        $this->assertSame(263.0, (float) $cheap->items[0]['price_inr']);
        Http::assertSent(fn (Request $q) => $q['url'] === 'https://www.myntra.com/running-shoes?rawQuery=running%20shoes&sort=price_asc&p=2'
            && ! isset($q['render']));

        $this->getJson('/api/v1/search?q=t-shirt&store=myntra')->assertOk();
    }

    public function test_a_blocked_listing_is_fetched_again_with_rendering_and_a_blocked_product_page_once_more(): void
    {
        $list = $this->fixture('myntra_listing_men_tshirts.html.gz');
        $tee = $this->fixture('myntra_product_12027436.html.gz');
        $block = '<!DOCTYPE html><html><head><title>Site Maintenance</title></head><body><h1>Oops! Something went wrong</h1></body></html>';
        $productCalls = 0;
        Http::fake([
            'realtime.oxylabs.io/*' => function (Request $request) use ($list, $tee, $block, &$productCalls) {
                $b = $request->data();
                if (preg_match('#/\d+$#', (string) $b['url'])) {
                    return Http::response($this->oxylabsRaw(++$productCalls === 1 ? $block : $tee));
                }

                return Http::response($this->oxylabsRaw(($b['render'] ?? null) === 'html' ? $list : $block));
            },
        ]);
        $src = app(OxylabsMyntraSource::class);

        $this->assertCount(32, $src->search('t-shirt')->items);
        Http::assertSent(fn (Request $q) => str_contains((string) $q['url'], 'rawQuery=t-shirt') && ! isset($q['render']));
        Http::assertSent(fn (Request $q) => str_contains((string) $q['url'], 'rawQuery=t-shirt') && ($q['render'] ?? null) === 'html');

        $this->assertSame(389.0, (float) $src->product(self::TEE)->data['price_inr']);
        $this->assertSame(2, $productCalls);
    }

    public function test_bestsellers_and_deals_use_category_listing_paths(): void
    {
        $this->fakeMyntra();
        $src = app(OxylabsMyntraSource::class);

        $best = $src->bestsellers('men-tshirts', 2);
        $this->assertCount(32, $best->items);
        Http::assertSent(fn (Request $q) => $q['url'] === 'https://www.myntra.com/men-tshirts?sort=popularity&p=2');

        config(['retail.offers.deal_urls.myntra' => ['https://www.myntra.com/men-tshirts?sort=discount']]);
        $deals = $src->deals();
        $this->assertNotEmpty($deals->items);
        $this->assertTrue(collect($deals->items)->every(fn ($r) => $r['price_inr'] <= 0.9 * $r['mrp_inr']), 'only real discounts');
        Http::assertSent(fn (Request $q) => $q['url'] === 'https://www.myntra.com/men-tshirts?sort=discount');
    }

    public function test_links_ids_and_admin_purchase_links(): void
    {
        Http::fake();
        $r = app(UrlResolver::class)->resolve('https://www.myntra.com/tshirts/levis/levis-men-pure-cotton-tshirt/12027436/buy?utm_source=share');
        $this->assertSame(['market' => 'myntra', 'id' => self::TEE], array_intersect_key((array) $r, ['market' => 1, 'id' => 1]));
        $this->assertSame(self::TEE, app(UrlResolver::class)->resolve('myntra.com/12027436')['id'] ?? null);
        Http::assertNothingSent();

        $this->assertSame('myntra', app(MarketResolver::class)->forId('my30250153'));
        $this->assertSame('https://www.myntra.com/12027436', Admin::sourceUrl('myntra', self::TEE));
    }

    public function test_marketplace_and_category_sources_exist(): void
    {
        $this->getJson('/api/v1/marketplaces')->assertOk()->assertJsonPath('items.2.id', 'myntra');
        $node = app(CategoryTree::class)->byPath('fashion/men/clothing/t-shirts-polos');
        $this->assertContains('myntra', $node['stores']);
        $this->assertNotContains('myntra', app(CategoryTree::class)->byPath('mobiles-electronics')['stores']);
    }
}
