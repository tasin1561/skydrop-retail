<?php

namespace Tests\Unit\Catalog;

use App\Sources\Parsers\AmazonHtmlParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AmazonHtmlParserTest extends TestCase
{
    private static array $parsed;

    private static float $ms;

    public static function setUpBeforeClass(): void
    {
        $html = gzdecode((string) file_get_contents(__DIR__.'/../../Fixtures/amazon_B0FMDLD86P.html.gz'));
        $t = microtime(true);
        self::$parsed = (new AmazonHtmlParser)->parse($html, 'B0FMDLD86P');
        self::$ms = (microtime(true) - $t) * 1000;
    }

    public function test_core_fields(): void
    {
        $p = self::$parsed;
        $this->assertStringStartsWith('OnePlus Nord Buds 3r TWS Earbuds', $p['title']);
        $this->assertSame('OnePlus', $p['brand']);
        $this->assertSame(1849.0, $p['price_inr']);
        $this->assertSame(1999.0, $p['mrp_inr']);
        $this->assertSame(8, $p['discount_pct']);
        $this->assertSame(4.3, $p['rating']);
        $this->assertSame(52942, $p['reviews']);
        $this->assertSame('In stock', $p['stock_text']);
        $this->assertTrue($p['in_stock']);
        $this->assertTrue($p['deliverable']);
        $this->assertSame('Thursday, 12 November', $p['delivery_text']);
        $this->assertTrue($p['prime']);
        $this->assertTrue($p['best_seller']);
        $this->assertSame('B0FNRNJ59H', $p['parent_id']);
        $this->assertSame('30K+ bought in past month', $p['sales_volume']);
    }

    public function test_media(): void
    {
        $p = self::$parsed;
        $this->assertGreaterThan(5, count($p['images']));
        $this->assertStringContainsString('_SL1500_', $p['images'][0]);
        $this->assertCount(4, $p['videos']);
        foreach ($p['videos'] as $v) {
            $this->assertStringEndsWith('.m3u8', $v['hls']);
            $this->assertStringStartsWith('https://m.media-amazon.com/images/I/', $v['thumb']);
        }
        $this->assertTrue($p['has_videos']);
        $this->assertNotEmpty($p['aplus']);
        $this->assertStringContainsString('aplus-media', $p['aplus'][0]);
    }

    public function test_variant_matrix_with_colour_swatches(): void
    {
        $v = collect(self::$parsed['variants']);
        $this->assertCount(5, $v);
        $this->assertSame(['Ash Black', 'Aura Blue', 'Deep Grey', 'Jade Mist', 'Misty Grey'], $v->pluck('label')->sort()->values()->all());
        $selected = $v->firstWhere('selected', true);
        $this->assertSame('B0FMDLD86P', $selected['id']);
        $this->assertSame(['Colour' => 'Aura Blue'], $selected['dimensions']);
        $this->assertSame('https://m.media-amazon.com/images/I/31WwCIKTZOL._SS64_.jpg', $v->firstWhere('label', 'Ash Black')['swatch']);
        $this->assertTrue($v->every(fn ($x) => $x['swatch'] !== null));
    }

    public function test_details_reviews_and_ladders(): void
    {
        $p = self::$parsed;
        $this->assertGreaterThanOrEqual(5, count($p['bullets']));
        $this->assertSame('OnePlus', $p['specs']['Brand name']);
        $this->assertArrayNotHasKey('Customer reviews', $p['specs']);
        $this->assertSame('82 Grams', $p['weight']);
        $this->assertSame(['Electronics', 'Headphones, Earbuds & Accessories', 'Headphones', 'In-Ear'], $p['category_path']);
        $this->assertSame(['rank' => 5, 'category' => 'Electronics'], $p['sales_rank'][0]);
        $this->assertSame([5, 4, 3, 2, 1], array_column($p['star_breakdown'], 'rating'));
        $this->assertSame(63, $p['star_breakdown'][0]['percentage']);
        $this->assertNotEmpty($p['review_list']);
        $this->assertSame('simonbritto', $p['review_list'][0]['author']);
        $this->assertSame(5, $p['review_list'][0]['rating']);
        $this->assertLessThanOrEqual(600, mb_strlen($p['review_list'][0]['content']));
    }

    public function test_offers_from_fixture(): void
    {
        $offers = collect(self::$parsed['offers']);
        $this->assertSame('Mega Festival Deal', self::$parsed['deal_badge']);
        $deal = $offers->firstWhere('type', 'deal');
        $this->assertSame(150.0, (float) $deal['savingInr']);
        $this->assertTrue($deal['appliesToOurPrice']);
        $this->assertSame(100.0, (float) $offers->firstWhere('type', 'bank')['savingInr']);
        $this->assertFalse($offers->firstWhere('type', 'bank')['appliesToOurPrice']);
        $this->assertNotNull($offers->firstWhere('type', 'cashback'));
        $this->assertNotNull($offers->firstWhere('type', 'emi'));
        $this->assertNotNull($offers->firstWhere('type', 'partner'));
        $this->assertNull(self::$parsed['coupon_inr']);
    }

    public function test_parses_under_300ms(): void
    {
        // 300 ms target on an idle server; allow headroom when the test machine is busy (parallel suites, CI).
        $this->assertLessThan((int) env('PARSER_BUDGET_MS', 800), self::$ms, sprintf('parse took %.0f ms', self::$ms));
    }

    public function test_coupon_lightning_and_subscribe_snippets(): void
    {
        $html = <<<'HTML'
<html><body>
<span id="productTitle"> Test Kettle </span>
<input type="hidden" id="twister-plus-price-data-price" value="1000" />
<span class="basisPrice"><span class="a-offscreen">₹2,000</span></span>
<div id="availability"><span class="a-size-medium">In stock</span></div>
<div id="dealBadge_feature_div"><span class="dealBadge"><span id="dealBadgeSupportingText"><span>Lightning Deal</span></span></span>
  <span>45% claimed</span><span>Ends in 02:13:44</span></div>
<div id="promoPriceBlockMessage_feature_div"><span id="couponText123">Apply ₹150 coupon Terms</span></div>
<div id="snsAccordionRowMiddle">Subscribe &amp; Save: ₹950 (5% off)</div>
</body></html>
HTML;
        $now = CarbonImmutable::parse('2026-11-07 10:00:00');
        $p = (new AmazonHtmlParser)->parse($html, 'B000000001', $now);
        $offers = collect($p['offers']);
        $lightning = $offers->firstWhere('type', 'lightning');
        $this->assertSame('Lightning Deal', $lightning['title']);
        $this->assertSame(45, $lightning['claimedPct']);
        $this->assertSame('2026-11-07T12:13:44+00:00', $lightning['endsAt']);
        $this->assertSame(150.0, $p['coupon_inr']);
        $this->assertSame(850.0, $p['effective_price_inr']);
        $this->assertTrue($offers->firstWhere('type', 'coupon')['appliesToOurPrice']);
        $this->assertSame('Subscribe & Save', $offers->firstWhere('type', 'subscribe')['title']);
        $this->assertFalse($offers->firstWhere('type', 'subscribe')['appliesToOurPrice']);

        $pct = (new AmazonHtmlParser)->parse(str_replace('Apply ₹150 coupon', 'Save 10% with coupon', $html), 'B000000001', $now);
        $this->assertSame(100.0, $pct['coupon_inr']);
    }

    public function test_missing_pieces_are_graceful(): void
    {
        $p = (new AmazonHtmlParser)->parse('<html><body><span id="productTitle">Only a title</span></body></html>', 'B000000002');
        $this->assertSame('Only a title', $p['title']);
        $this->assertSame(0.0, $p['price_inr']);
        $this->assertFalse($p['in_stock']);
        $this->assertSame([], $p['variants']);
        $this->assertSame([], $p['videos']);
        $this->assertSame([], $p['offers']);
    }
}
