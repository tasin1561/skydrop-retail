<?php

namespace Tests\Unit\Catalog;

use App\Services\Catalog\CategoryGuesser;
use App\Sources\Exceptions\ProductGoneException;
use App\Sources\OxylabsMyntraSource;
use App\Sources\Parsers\MyntraParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Real myntra.com pages (Nov 2026) fetched through Oxylabs. */
class MyntraRealPagesTest extends TestCase
{
    private function html(string $file): string
    {
        return (string) gzdecode((string) file_get_contents(__DIR__.'/../../Fixtures/'.$file));
    }

    public function test_listing_cards(): void
    {
        $page = (new MyntraParser)->listing($this->html('myntra_listing_men_tshirts.html.gz'));
        $this->assertCount(32, $page->items);
        $this->assertGreaterThan(1, $page->pages);
        $first = $page->items[0];
        $this->assertSame('my12027436', $first['id']);
        $this->assertSame('myntra', $first['market']);
        $this->assertSame('Levis Men Soft Pure Cotton Round Neck Half Sleeve Tshirt', $first['title']);
        $this->assertSame([389.0, 649.0], [(float) $first['price_inr'], (float) $first['mrp_inr']]);
        $this->assertSame(35943, $first['reviews']);
        $this->assertStringStartsWith('https://assets.myntassets.com/', $first['image']);
        $this->assertSame('40% OFF', $first['deal_badge']);
        $this->assertTrue(collect($page->items)->every(fn ($r) => $r['price_inr'] > 0 && $r['price_inr'] <= $r['mrp_inr']));
    }

    public function test_product_page(): void
    {
        $p = (new MyntraParser)->product($this->html('myntra_product_12027436.html.gz'), 'my12027436');
        $this->assertSame('Levis Men Soft Pure Cotton Round Neck Half Sleeve Tshirt', $p['title']);
        $this->assertSame([389.0, 649.0], [(float) $p['price_inr'], (float) $p['mrp_inr']]);
        $this->assertSame(4.4, $p['rating']);
        $this->assertCount(7, $p['images']);
        $this->assertSame(['Men', 'Apparel', 'Loungewear and Nightwear', 'Lounge Tshirts'], $p['category_path']);
        $this->assertSame('Cotton', $p['specs']['Fabrics']);
        $this->assertSame('14 days', $p['specs']['Myntra returns']);
        $this->assertCount(5, $p['star_breakdown']);
        $this->assertNotEmpty($p['review_list']);
        $this->assertNull($p['weight'], 'no weight on Myntra: category default applies');
        $this->assertSame('fashion', (new CategoryGuesser)->guess('myntra', $p['title'], $p['category_path']));
    }

    public function test_sold_out_sizes_and_out_of_stock_styles(): void
    {
        $html = $this->html('myntra_product_12027436.html.gz');
        // Mark size S sold out, as Myntra does ("available": false on that size).
        $soldOutS = preg_replace('/("label":"S","available":)true/', '$1false', $html, 1);
        $p = (new MyntraParser)->product($soldOutS, 'my12027436');
        $sizes = collect($p['variants'])->whereNull('id')->keyBy('label');
        $this->assertFalse($sizes['White / S']['in_stock']);
        $this->assertFalse($sizes['White / S']['selected']);
        $this->assertTrue($sizes['White / M']['selected'], 'the first size in stock is preselected');
        $this->assertTrue($p['in_stock']);

        $allGone = preg_replace('/("label":"(?:S|M|L|XL)","available":)true/', '$1false', $html);
        $this->assertFalse((new MyntraParser)->product($allGone, 'my12027436')['in_stock']);
    }

    public function test_kids_items_get_our_kids_category_names(): void
    {
        $this->assertSame(['Kids', 'Apparel', 'Topwear', 'Kurtas', 'Girls Clothing'], MyntraParser::path('Girls', 'Apparel', 'Topwear', 'Kurtas'));
        $this->assertSame(['Kids', 'Footwear', 'Kids Footwear'], MyntraParser::path('Boys', 'Footwear', 'Shoes', 'Sports Shoes'));
        $this->assertSame(['Women', 'Personal Care', 'Lips', 'Lipstick'], MyntraParser::path('Women', 'Personal Care', 'Lips', 'Lipstick'));
        $this->assertSame(['Home', 'Bedding', 'Pillows'], MyntraParser::path('Unisex', 'Home', 'Bedding', 'Pillows'));
        $guess = new CategoryGuesser;
        $this->assertSame('fashion', $guess->guess('myntra', 'Girls Kurta', MyntraParser::path('Girls', 'Apparel', 'Topwear', 'Kurtas')));
        $this->assertSame('beauty', $guess->guess('myntra', 'Lakme Lipstick', MyntraParser::path('Women', 'Personal Care', 'Lips', 'Lipstick')));
        $this->assertSame('home', $guess->guess('myntra', 'Pillow', MyntraParser::path('Unisex', 'Home', 'Bedding', 'Pillows')));
    }

    public function test_colours_with_the_same_name_stay_separate(): void
    {
        $html = $this->html('myntra_product_12027436.html.gz');
        $html = str_replace(['"label":"Charcoal"', '"label":"Red"'], ['"label":"Navy Blue"', '"label":"White"'], $html);
        $labels = array_column(array_filter((new MyntraParser)->product($html, 'my12027436')['variants'], fn ($v) => $v['id'] !== null), 'label');
        $this->assertContains('Navy Blue', $labels);
        $this->assertContains('Navy Blue (2)', $labels);
        $this->assertContains('White (2)', $labels, 'never the same name as the current colour');
        $this->assertSame(count($labels), count(array_unique($labels)));
    }

    public function test_ids_and_urls(): void
    {
        $this->assertSame('12027436', MyntraParser::styleId('my12027436'));
        $this->assertSame('https://www.myntra.com/12027436', MyntraParser::productUrl('my12027436'));
        $this->assertSame('my47999156', MyntraParser::idFromUrl('https://www.myntra.com/tshirts/roadster/roadster-men--pack-of--2-printed-t-shirt/47999156/buy'));
        $this->assertSame('my47999156', MyntraParser::idFromUrl('https://www.myntra.com/47999156'));
        $this->assertNull(MyntraParser::idFromUrl('https://www.myntra.com/men-tshirts'));
    }

    public function test_removed_style_is_reported_as_gone(): void
    {
        config(['retail.oxylabs.token' => 'test-token', 'retail.oxylabs.hedge_after' => 0]);
        $shell = '<html><body>'.str_repeat('x', 2000).'<script>window.__myx = {"pdpData":null,"pageName":"pdp"}</script></body></html>';
        Http::fake(['realtime.oxylabs.io/*' => Http::response(['results' => [['content' => $shell, 'status_code' => 200]]])]);

        $this->expectException(ProductGoneException::class);
        app(OxylabsMyntraSource::class)->product('my99999999');
    }
}
