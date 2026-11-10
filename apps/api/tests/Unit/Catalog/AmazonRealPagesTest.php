<?php

namespace Tests\Unit\Catalog;

use App\Services\Catalog\CategoryGuesser;
use App\Sources\Parsers\AmazonHtmlParser;
use App\Sources\Support\Offers;
use Tests\TestCase;

/** Real amazon.in product pages (Nov 2026) fetched through Oxylabs with the Kolkata pincode. */
class AmazonRealPagesTest extends TestCase
{
    private function parse(string $file, string $asin): array
    {
        return app(AmazonHtmlParser::class)->parse((string) gzdecode((string) file_get_contents(__DIR__.'/../../Fixtures/'.$file)), $asin);
    }

    public function test_other_products_coupons_in_carousels_are_not_this_products_coupon(): void
    {
        // The page has "You pay ₹38,999.00 with coupon" for OTHER phones in a carousel. Reading it as this
        // phone's coupon priced a ₹26,499 phone at ₹1.
        $p = $this->parse('amazon_B0GVYXPZBS_carousel_coupons.html.gz', 'B0GVYXPZBS');
        $this->assertSame(26499.0, $p['price_inr']);
        $this->assertNull($p['coupon_inr']);
        $this->assertNull($p['effective_price_inr']);
        $this->assertNotContains('coupon', array_column($p['offers'], 'type'));
        $this->assertSame('208 Grams', $p['weight']);
        $this->assertCount(5, $p['variants']);
    }

    public function test_percent_coupon_uses_the_coupon_percent_not_the_savings_percent(): void
    {
        // "80% Savings … Apply 2% coupon": 2% of ₹300 = ₹6 (was read as ₹246).
        $p = $this->parse('amazon_B0GW3H8KXY_pct_coupon.html.gz', 'B0GW3H8KXY');
        $this->assertSame(300.0, $p['price_inr']);
        $this->assertSame(6.0, $p['coupon_inr']);
        $this->assertSame(294.0, $p['effective_price_inr']);
        $this->assertSame('GLAMALUE', $p['brand']);
        $this->assertStringStartsWith('GLAMALUE ', $p['title'], 'fashion titles get their brand');
        $offers = array_map(fn ($o) => $o['type'].'|'.$o['title'].'|'.($o['detail'] ?? ''), $p['offers']);
        $this->assertSame(count($offers), count(array_unique($offers)), 'no duplicate offers');
    }

    public function test_book_author_and_category(): void
    {
        $p = $this->parse('amazon_1847941834_book.html.gz', '1847941834');
        $this->assertSame('James Clear', $p['brand']);
        $this->assertSame('Atomic Habits: Tiny Changes, Remarkable Results', $p['title'], 'no author prefix on book titles');
        $this->assertSame('395 g', $p['weight']);
        $this->assertSame('home', (new CategoryGuesser)->guess('amazon', $p['title'], $p['category_path']), 'Books > Health… is a book, not beauty');
    }

    public function test_coupon_phrases(): void
    {
        $this->assertSame([500.0, 'Apply ₹500 coupon'], AmazonHtmlParser::couponFrom('Apply ₹500 coupon Terms', 2000));
        $this->assertSame([6.0, 'Apply 2% coupon'], AmazonHtmlParser::couponFrom('80% Savings List Price: ₹1,499.00 Apply 2% coupon', 300));
        $this->assertSame([100.0, 'Save ₹100 with coupon'], AmazonHtmlParser::couponFrom('Save ₹100 with coupon', 999));
        $this->assertSame([200.0, 'You pay ₹1,299.00 with coupon'], AmazonHtmlParser::couponFrom('You pay ₹1,299.00 with coupon', 1499));
        $this->assertSame([null, ''], AmazonHtmlParser::couponFrom('You pay ₹38,999.00 with coupon', 26499), 'more than the price: another item');
        $this->assertSame([null, ''], AmazonHtmlParser::couponFrom('Apply ₹900 coupon', 1000), 'over 60% of the price: misread');
        $this->assertNull(Offers::effectivePrice(290, 281.3));
        $this->assertSame(294.0, Offers::effectivePrice(300, 6));
    }
}
