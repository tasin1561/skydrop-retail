<?php

namespace Tests\Unit\Catalog;

use App\Sources\Parsers\FlipkartHtmlParser;
use PHPUnit\Framework\TestCase;

/** Real flipkart.com pages (Nov 2026 "Atlas" layout) fetched through Oxylabs without JS rendering. */
class FlipkartRealPagesTest extends TestCase
{
    private function page(string $name): string
    {
        return (string) gzdecode((string) file_get_contents(__DIR__.'/../../Fixtures/'.$name));
    }

    public function test_fashion_product_page(): void
    {
        $p = (new FlipkartHtmlParser)->product($this->page('flipkart_product_shoe_SHOHAHU3YXJTAHYF.html.gz'), 'itm9e64270628c7b-shohahu3yxjtahyf');

        $this->assertSame('Asics GT-2000 13 Running Shoes For Men', $p['title']);
        $this->assertSame('Asics', $p['brand']);
        $this->assertSame(5199.0, $p['price_inr']);
        $this->assertSame(12999.0, $p['mrp_inr']);
        $this->assertTrue($p['in_stock']);
        $this->assertTrue($p['deliverable'], 'the "Not deliverable at your location" line (no pincode) must not hide the product');
        $this->assertSame(['Footwear', "Men's Footwear", "Men's Sports Shoes"], $p['category_path']);
        $this->assertGreaterThanOrEqual(4, count($p['images']));
        $this->assertStringContainsString('/image/1500/1500/', $p['images'][0]);
        $this->assertStringEndsWith('manifest.m3u8', $p['videos'][0]['hls']);
        $this->assertTrue($p['has_videos']);
        $this->assertCount(8, $p['review_list']);
        $this->assertSame(5, $p['review_list'][0]['rating']);
        $this->assertNotSame('', $p['review_list'][0]['content']);
        $this->assertGreaterThan(15, count($p['specs']));

        // Colours and sizes, each option its own pid, with Flipkart's stock flags.
        $v = collect($p['variants'])->keyBy('id');
        $this->assertTrue($v['itm9e64270628c7b-shohahu3yxjtahyf']['selected']);
        $this->assertSame(['Color' => 'BLACK/WHITE', 'Size' => '8'], $v['itm9e64270628c7b-shohahu3yxjtahyf']['dimensions']);
        $this->assertSame(['Color' => 'BLACK/WHITE', 'Size' => '9'], $v['itm9e64270628c7b-shohahu3pbp7qjky']['dimensions']);
        $this->assertFalse($v['itm4f95c07cc77cd-shoh389bh8p3thpm']['in_stock'], 'size 5 is out of stock');
        $this->assertTrue($v['itm117e2ef2b6e9f-shohgdndmmng5qap']['in_stock']);
        $this->assertNotNull($v['itm117e2ef2b6e9f-shohgdndmmng5qap']['swatch']);

        // Bank and exchange offers are shown "on Flipkart" only.
        $types = array_column($p['offers'], 'type');
        $this->assertContains('bank', $types);
        $this->assertContains('exchange', $types);
        $this->assertFalse(collect($p['offers'])->contains('appliesToOurPrice', true));
    }

    public function test_out_of_stock_phone_without_pid_opens_flipkarts_default_variant(): void
    {
        $p = (new FlipkartHtmlParser)->product($this->page('flipkart_product_phone_oos_itm94360d23ec184.html.gz'), 'itm94360d23ec184');

        $this->assertSame('Samsung Galaxy M35 5G (Thunder Grey, 256 GB)', $p['title']);
        $this->assertSame(18199.0, $p['price_inr']);
        $this->assertSame(27499.0, $p['mrp_inr'], 'MRP of the phone, not of a warranty add-on');
        $this->assertFalse($p['in_stock']);
        $this->assertSame(['Mobiles & Accessories', 'Mobiles'], $p['category_path']);
        $this->assertSame(['Color', 'Variant'], array_keys($p['variants'][0]['dimensions']));
        // UPI apps with the same saving are grouped into one line.
        $bank = collect($p['offers'])->where('type', 'bank')->pluck('detail')->all();
        $this->assertContains('Flipkart SBI: ₹1,385 off', $bank);
        $this->assertTrue(collect($bank)->contains(fn ($d) => str_contains($d, 'more: ₹182 off')));
        $this->assertLessThanOrEqual(6, count($bank));
    }

    public function test_grid_search_cards(): void
    {
        $rows = (new FlipkartHtmlParser)->listing($this->page('flipkart_search_running_shoes_grid.html.gz'))->items;

        $this->assertCount(40, $rows);
        $first = $rows[0];
        $this->assertSame('itm9e64270628c7b-shohahu3yxjtahyf', $first['id']);
        $this->assertSame('Asics GT-2000 13 Running Shoes For Men', $first['title']);
        $this->assertSame('Asics', $first['brand']);
        $this->assertSame(5199.0, $first['price_inr']);
        $this->assertSame(12999.0, $first['mrp_inr']);
        foreach ($rows as $r) {
            $this->assertMatchesRegularExpression('#^https://rukminim\d\.flixcart\.com/image/#', (string) $r['image'], "image for {$r['id']}");
        }
    }

    public function test_list_search_cards_ignore_exchange_amounts(): void
    {
        $rows = collect((new FlipkartHtmlParser)->listing($this->page('flipkart_search_smart_tv_list.html.gz'))->items)->keyBy('id');

        $this->assertCount(24, $rows);
        $tv = $rows['itm65659ce812c21-tvshmhkp5gfwzfaz'];
        $this->assertSame(30490.0, $tv['price_inr']);
        $this->assertSame(39900.0, $tv['mrp_inr'], 'not the "Upto ₹4,150 off on exchange" amount');
        $this->assertSame(4.3, $tv['rating']);
        $this->assertSame(87313, $tv['reviews']);
        $this->assertTrue($rows->every(fn ($r) => $r['mrp_inr'] >= $r['price_inr'] && $r['image']));
    }
}
