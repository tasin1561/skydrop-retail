<?php

namespace Tests\Unit\Catalog;

use App\Sources\Parsers\AmazonListingMapper;
use App\Sources\Parsers\BigBasketParser;
use App\Sources\Parsers\FlipkartHtmlParser;
use App\Sources\Support\Offers;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ListingAndStoreParsersTest extends TestCase
{
    private function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/'.$name), true);
    }

    public function test_amazon_search_listing_keeps_coupons_and_skips_sponsored(): void
    {
        $page = (new AmazonListingMapper)->search($this->fixture('oxylabs_amazon_search_earbuds.json')['results'][0]['content']);
        $this->assertGreaterThanOrEqual(15, count($page->items));
        $this->assertSame(50000, $page->total);
        $first = $page->items[0];
        $this->assertSame('B0FMDLD86P', $first['id']);
        $this->assertSame(1849.0, $first['price_inr']);
        $this->assertSame(1999.0, $first['mrp_inr']);
        $this->assertTrue($first['best_seller']);
        $coupon = collect($page->items)->firstWhere('id', 'B0HHNTFNGR');
        $this->assertSame(281.3, $coupon['coupon_inr']);
        $this->assertSame('coupon', $coupon['offers'][0]['type']);
        $this->assertSame(round($coupon['price_inr'] - 281.3, 2), $coupon['effective_price_inr']);
        $this->assertNull(collect($page->items)->firstWhere('id', 'B0GKY99P56'), 'sponsored rows are skipped');
    }

    public function test_amazon_bestsellers(): void
    {
        $page = (new AmazonListingMapper)->bestsellers($this->fixture('oxylabs_amazon_bestsellers_electronics.json')['results'][0]['content']);
        $this->assertGreaterThan(40, count($page->items));
        $this->assertSame('B0GVYXPZBS', $page->items[0]['id']);
        $this->assertSame(1, $page->items[0]['rank']);
    }

    public function test_flipkart_product_and_available_offers(): void
    {
        $html = <<<'HTML'
<html><head><script type="application/ld+json">[{"@type":"Product","name":"Men Slim Fit Shirt","brand":{"@type":"Brand","name":"Northline"},
"image":["https://rukminim2.flixcart.com/a.jpg","https://rukminim2.flixcart.com/b.jpg"],
"offers":{"@type":"Offer","price":699,"priceCurrency":"INR","availability":"http://schema.org/InStock"},
"aggregateRating":{"ratingValue":4.2,"reviewCount":1820}}]</script>
<script>window.__INITIAL_STATE__ = {"pricing":{"mrp":{"value":1499}}};</script></head>
<body><h1>Men Slim Fit Shirt</h1>
<div><div>Available offers</div><ul>
<li><span>Bank Offer</span><span>10% off on HDFC Bank Credit Card, up to ₹1,250</span> T&amp;C</li>
<li><span>Special Price</span><span>Get extra 53% off (price inclusive of cashback/coupon)</span></li>
<li><span>Partner Offer</span><span>Sign up for Pay Later and get Gift Card worth up to ₹500</span></li>
<li><span>Combo Offer</span><span>Buy 2 items save 5%; Buy 3 save 8%</span></li>
</ul></div>
<table><tr><td>Fabric</td><td>Pure Cotton</td></tr><tr><td>Fit</td><td>Slim</td></tr></table>
</body></html>
HTML;
        $p = (new FlipkartHtmlParser)->product($html, 'itm61400c2ed6802');
        $this->assertSame('Men Slim Fit Shirt', $p['title']);
        $this->assertSame('Northline', $p['brand']);
        $this->assertSame(699.0, $p['price_inr']);
        $this->assertSame(1499.0, $p['mrp_inr']);
        $this->assertSame(4.2, $p['rating']);
        $this->assertCount(2, $p['images']);
        $this->assertSame('Pure Cotton', $p['specs']['Fabric']);
        $types = array_column($p['offers'], 'type');
        $this->assertSame(['bank', 'special_price', 'partner', 'combo'], $types);
        $this->assertSame(1250.0, $p['offers'][0]['savingInr']);
        $this->assertFalse($p['offers'][0]['appliesToOurPrice']);
        $this->assertTrue($p['offers'][1]['appliesToOurPrice']);
        $this->assertSame('Special Price', $p['deal_badge']);
    }

    public function test_flipkart_listing_cards(): void
    {
        $html = '<div data-id="SHTX"><a href="/northline-shirt/p/itm61400c2ed6802?pid=SHTX" title="Northline Shirt"><img src="https://img/a.jpg" alt="Northline Shirt"></a>'
            .'<div>4.2</div><span>1,820 Ratings</span><div>₹699</div><div>₹1,499</div></div>';
        $rows = (new FlipkartHtmlParser)->listing($html)->items;
        $this->assertCount(1, $rows);
        $this->assertSame('itm61400c2ed6802', $rows[0]['id']);
        $this->assertSame(699.0, $rows[0]['price_inr']);
        $this->assertSame(1499.0, $rows[0]['mrp_inr']);
        $this->assertSame(1820, $rows[0]['reviews']);
    }

    public function test_bigbasket_next_data_with_bogo_tag(): void
    {
        $state = ['props' => ['pageProps' => ['productDetails' => ['children' => [[
            'id' => 40001234, 'desc' => 'Basmati Rice', 'w' => '1 kg', 'brand' => ['name' => 'India Gate'],
            'pricing' => ['discount' => ['mrp' => '250', 'prim_price' => ['sp' => '199'], 'd_text' => '20% OFF']],
            'images' => [['l' => 'https://www.bigbasket.com/media/l.jpg']],
            'tags' => [['header' => 'Buy 1 Get 1 Free']],
            'rating_info' => ['avg_rating' => 4.3, 'rating_count' => 210],
        ]]]]]];
        $html = '<html><body><script id="__NEXT_DATA__" type="application/json">'.json_encode($state).'</script></body></html>';
        $p = (new BigBasketParser)->product($html, 'bb40001234');
        $this->assertSame('Basmati Rice 1 kg', $p['title']);
        $this->assertSame('India Gate', $p['brand']);
        $this->assertSame(199.0, $p['price_inr']);
        $this->assertSame(250.0, $p['mrp_inr']);
        $this->assertSame('1 kg', $p['weight']);
        $this->assertSame('20% OFF', $p['deal_badge']);
        $this->assertSame(['deal', 'combo'], array_column($p['offers'], 'type'));
        $this->assertFalse($p['offers'][1]['appliesToOurPrice']);

        $list = (new BigBasketParser)->listing($html)->items;
        $this->assertSame('bb40001234', $list[0]['id']);
    }

    public function test_offer_helpers(): void
    {
        $now = CarbonImmutable::parse('2026-11-07 10:00:00');
        $this->assertSame('2026-11-07 13:20:00', Offers::endsIn('Ends in 3h 20m', $now)?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-07 10:45:00', Offers::endsIn('Deal ends in 45 mins', $now)?->format('Y-m-d H:i:s'));
        $this->assertNull(Offers::endsIn('Limited time deal', $now));
        $this->assertSame('emi', Offers::classify('No Cost EMI'));
        $this->assertSame('combo', Offers::classify('Buy 1 Get 1'));
        $this->assertSame(500.0, Offers::couponValue('Apply ₹500 coupon', 2000));
        $this->assertSame(200.0, Offers::couponValue('Save 10% with coupon', 2000));
    }
}
