<?php

namespace Tests\Unit\Catalog;

use App\Services\Catalog\CategoryGuesser;
use App\Sources\Parsers\BigBasketParser;
use Tests\TestCase;

/** Real bigbasket.com pages (Nov 2026) fetched through Oxylabs. */
class BigBasketRealPagesTest extends TestCase
{
    private function html(string $file): string
    {
        return (string) gzdecode((string) file_get_contents(__DIR__.'/../../Fixtures/'.$file));
    }

    public function test_desktop_product_page_with_pack_sizes(): void
    {
        $p = (new BigBasketParser)->product($this->html('bigbasket_product_10000148_onion.html.gz'), 'bb10000148');
        $this->assertSame('fresho! Onion 1 kg', $p['title'], 'brand added, as the store shows it');
        $this->assertSame([65.0, 85.0], [(float) $p['price_inr'], (float) $p['mrp_inr']]);
        $this->assertSame('1 kg', $p['weight']);
        $this->assertSame(['Fruits & Vegetables', 'Fresh Vegetables', 'Potato, Onion & Tomato'], $p['category_path']);
        $this->assertSame(['deal'], array_column($p['offers'], 'type'), 'collection tags (WORLD FOOD DAY, DIWALI…) are not offers');
        $this->assertStringStartsWith('fresho! Onion brings freshness and flavour straight to your kitchen.', $p['bullets'][0], 'hard-wrapped text joined');
        $packs = collect($p['variants']);
        $this->assertSame(['1 kg', '500 g', '2 kg', '2 kg (2)', '5 kg'], $packs->pluck('label')->all());
        $this->assertSame('bb10000148', $packs->firstWhere('selected', true)['id']);
        $this->assertSame('grocery', (new CategoryGuesser)->guess('bigbasket', $p['title'], $p['category_path']));

        $rice = (new BigBasketParser)->product($this->html('bigbasket_product_40110353_rice_packs.html.gz'), 'bb40110353');
        $this->assertSame([515.0, 745.0], [(float) $rice['price_inr'], (float) $rice['mrp_inr']]);
        $this->assertCount(3, $rice['variants']);
        $this->assertSame('Basmati Rice', end($rice['category_path']));
    }

    public function test_mobile_category_and_brand_pages_carry_the_products(): void
    {
        $rice = (new BigBasketParser)->listing($this->html('bigbasket_mobile_category_rice.html.gz'))->items;
        $this->assertCount(136, $rice);
        $first = $rice[0];
        $this->assertSame('bb40075196', $first['id']);
        $this->assertSame('Daawat Rozana Super Basmati Rice 1 kg', $first['title']);
        $this->assertSame([92.0, 100.0], [(float) $first['price_inr'], (float) $first['mrp_inr']]);
        $this->assertStringStartsWith('https://www.bbassets.com/', $first['image']);

        $haldirams = (new BigBasketParser)->listing($this->html('bigbasket_mobile_brand_haldirams.html.gz'))->items;
        $this->assertGreaterThan(80, count($haldirams));
        $this->assertTrue(collect($haldirams)->every(fn ($r) => $r['price_inr'] > 1), 'placeholder ₹1 listings are dropped');
        $this->assertTrue(collect($haldirams)->every(fn ($r) => str_starts_with($r['title'], 'Haldiram')));
    }

    public function test_desktop_search_page_has_no_products_and_mobile_product_page_only_a_price(): void
    {
        $this->assertSame([], (new BigBasketParser)->listing($this->html('bigbasket_desktop_search_no_products.html.gz'))->items);
        $this->assertSame(['title' => 'Fresho Onion 1 Kg', 'price' => 66.0], BigBasketParser::mobileTitle($this->html('bigbasket_mobile_product_10000148.html.gz')));
        $this->assertSame('', (new BigBasketParser)->product($this->html('bigbasket_mobile_product_10000148.html.gz'), 'bb10000148')['title']);
    }

    public function test_offer_tags(): void
    {
        foreach (['Buy 1 Get 1 Free', 'Save ₹20', '10% OFF on 2', 'Combo offer', 'Get 1 free'] as $tag) {
            $this->assertTrue(BigBasketParser::isOffer($tag), $tag);
        }
        foreach (['PRESERVATIVE FREE', 'WORLD FOOD DAY', 'DIWALI', 'Sugar Free', 'Gluten free'] as $tag) {
            $this->assertFalse(BigBasketParser::isOffer($tag), $tag);
        }
    }
}
