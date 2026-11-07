<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/** GET /search?brand= and /products/popular?brand=, plus the brand facets (docs/API.md "Search"). */
class BrandFilterTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        Http::preventStrayRequests();
    }

    private function brandsOf(array $items): array
    {
        return array_values(array_unique(array_map(fn ($p) => $p['brand'], $items)));
    }

    public function test_single_brand_is_a_case_insensitive_exact_match(): void
    {
        $r = $this->getJson('/api/v1/search?brand=nike')->assertOk();
        $this->assertSame(7, $r->json('total'));
        $this->assertSame(['Nike'], $this->brandsOf($r->json('items')));

        // exact, not a substring: "Nik" matches nothing
        $this->assertSame(0, $this->getJson('/api/v1/search?brand=Nik')->json('total'));
    }

    public function test_multiple_brands_are_ored(): void
    {
        $r = $this->getJson('/api/v1/search?brand='.urlencode('Campus, RED TAPE,campus'))->assertOk();
        $this->assertSame(15, $r->json('total'));
        $brands = $this->brandsOf($r->json('items'));
        sort($brands);
        $this->assertSame(['Campus', 'Red Tape'], $brands);
    }

    public function test_facets_list_top_brands_ignoring_the_brand_filter(): void
    {
        $all = $this->getJson('/api/v1/search?store=amazon')->assertOk()->json('facets.brands');
        $this->assertLessThanOrEqual(CatalogQuery::BRAND_FACETS, count($all));
        $this->assertSame(['name' => 'Campus', 'count' => 8], $all[0]);
        $this->assertNotContains('', array_column($all, 'name'));
        $counts = array_column($all, 'count');
        $sorted = $counts;
        rsort($sorted);
        $this->assertSame($sorted, $counts);

        // the same facet list comes back with a brand selected, so the shopper can switch brands
        $filtered = $this->getJson('/api/v1/search?store=amazon&brand=Nike')->assertOk();
        $this->assertSame($all, $filtered->json('facets.brands'));
        $this->assertSame(7, $filtered->json('total'));
        $this->assertArrayHasKey('categories', $filtered->json('facets'));
    }

    public function test_facets_follow_the_other_filters_and_counts_are_capped(): void
    {
        $electronics = $this->getJson('/api/v1/search?category=electronics')->json('facets.brands');
        $names = array_column($electronics, 'name');
        $this->assertContains('OnePlus', $names);
        $this->assertNotContains('Campus', $names);

        Product::query()->where('brand', 'Campus')->update(['brand' => 'Bulk']);
        Product::query()->where('brand', '')->orWhereNull('brand')->update(['brand' => 'Bulk']);
        $this->assertSame(0, count(array_filter(
            $this->getJson('/api/v1/search?q=shirt')->json('facets.brands'),
            fn ($b) => $b['count'] > CatalogQuery::BRAND_COUNT_CAP,
        )));
    }

    public function test_brand_combines_with_query_store_category_price_rating_sort_and_page(): void
    {
        $r = $this->getJson('/api/v1/search?q=shoes&store=amazon&category=fashion&brand=Nike,adidas&sort=price_asc&min=1&max=100000&rating=1')->assertOk();
        $items = $r->json('items');
        $this->assertNotEmpty($items);
        foreach ($items as $p) {
            $this->assertContains($p['brand'], ['Nike', 'adidas']);
            $this->assertSame('amazon', $p['market']);
            $this->assertSame('fashion', $p['category']);
        }
        $prices = array_column($items, 'priceInr');
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices);

        $this->assertSame(0, $this->getJson('/api/v1/search?store=flipkart&brand=Nike')->json('total'));
        $this->assertSame(0, $this->getJson('/api/v1/search?category=grocery&brand=Nike')->json('total'));
        $this->getJson('/api/v1/search?brand=Nike&page=2')->assertOk()->assertJsonPath('items', [])->assertJsonPath('total', 7);
    }

    public function test_validation(): void
    {
        $eleven = implode(',', array_map(fn ($i) => "b$i", range(1, 11)));
        $this->getJson("/api/v1/search?brand=$eleven")->assertStatus(422)->assertJsonValidationErrors('brand');
        $this->getJson('/api/v1/search?brand='.str_repeat('x', 61))->assertStatus(422)->assertJsonValidationErrors('brand');
        $this->getJson('/api/v1/search?brand[]=Nike')->assertStatus(422)->assertJsonValidationErrors('brand');
        $this->getJson('/api/v1/products/popular?brand='.$eleven)->assertStatus(422);

        // ten names (duplicates collapse first) and empty parts are fine
        $ten = implode(',', array_map(fn ($i) => "b$i", range(1, 10)));
        $this->getJson("/api/v1/search?brand=$ten,b1,,")->assertOk();
        $this->getJson('/api/v1/search?brand=')->assertOk()->assertJsonPath('total', $this->getJson('/api/v1/search')->json('total'));
    }

    public function test_popular_accepts_brand_for_store_browse(): void
    {
        $items = $this->getJson('/api/v1/products/popular?store=amazon&brand=puma,reebok&limit=10')->assertOk()->json('items');
        $this->assertCount(4, $items);
        $brands = $this->brandsOf($items);
        sort($brands);
        $this->assertSame(['PUMA', 'Reebok'], $brands);
        $this->assertSame([], $this->getJson('/api/v1/products/popular?store=flipkart&brand=PUMA')->json('items'));
    }

    public function test_database_fallback_supports_brand(): void
    {
        $this->mock(CatalogService::class, fn ($m) => $m->shouldReceive('search')->andThrow(new \RuntimeException('queue down')));
        $r = $this->getJson('/api/v1/search?brand=Nike')->assertOk()->assertJsonPath('stale', true);
        $this->assertSame(7, $r->json('total'));
        $this->assertSame(['Nike'], $this->brandsOf($r->json('items')));
        $this->assertSame('Campus', $r->json('facets.brands.0.name'));
        Mockery::close();
    }

    public function test_parse_brands(): void
    {
        $this->assertSame(['Nike', 'red tape'], CatalogQuery::parseBrands(' Nike ,red  tape ,NIKE,, Red Tape'));
        $this->assertSame([], CatalogQuery::parseBrands(null));
        $this->assertNull(CatalogQuery::parseBrands(str_repeat('a', 61)));
    }
}
