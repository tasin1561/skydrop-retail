<?php

namespace Tests\Feature\Catalog;

use App\Sources\OxylabsAmazonSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The exact Oxylabs requests for Amazon (Oxylabs rejects unknown values with HTTP 400). */
class AmazonRequestsTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->goLive('amazon');
    }

    public function test_sorted_search_uses_the_sort_names_oxylabs_accepts(): void
    {
        $this->fakeOxylabs();
        $src = app(OxylabsAmazonSource::class);
        foreach (['price_asc' => 'price_low_to_high', 'price_desc' => 'price_high_to_low', 'rating' => 'average_review', 'newest' => 'most_recent'] as $ours => $theirs) {
            $this->assertNotEmpty($src->search('earbuds', 2, $ours)->items);
            Http::assertSent(fn (Request $q) => $q['source'] === 'amazon_search' && ($q['start_page'] ?? null) === 2
                && ($q['context'][0] ?? null) === ['key' => 'sort_by', 'value' => $theirs]);
        }
        $src->search('earbuds');
        Http::assertSent(fn (Request $q) => $q['source'] === 'amazon_search' && ! isset($q['context']) && ! isset($q['start_page']));
    }

    public function test_deals_page_uses_the_amazon_source_without_rendering(): void
    {
        Http::fake(['realtime.oxylabs.io/*' => Http::response($this->oxylabsRaw(
            '<html><body>'.str_repeat('x', 1200).'<div data-asin="B0FMDLD86P"></div><a href="/dp/B0GVYXPZBS">x</a></body></html>'))]);

        $page = app(OxylabsAmazonSource::class)->deals();
        $this->assertSame(['B0FMDLD86P', 'B0GVYXPZBS'], collect($page->items)->pluck('id')->sort()->values()->all());
        Http::assertSent(fn (Request $q) => $q['source'] === 'amazon' && $q['url'] === 'https://www.amazon.in/deals'
            && $q['geo_location'] === '700001' && ! isset($q['render']));
    }
}
