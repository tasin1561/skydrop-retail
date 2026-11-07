<?php

namespace Tests\Feature\Shopping;

use App\Models\Bundle;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundlesTest extends TestCase
{
    use RefreshDatabase, ShoppingHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopping();
    }

    public function test_bought_together_uses_copurchases_then_complements(): void
    {
        $u = $this->shopper();
        $phone = $this->product('BP1', 20000, 'amazon', 'electronics', ['title' => 'Galaxy phone', 'reviews' => 10]);
        $case = $this->product('BC1', 500, 'amazon', 'electronics', ['title' => 'Silicone back cover', 'reviews' => 5]);
        $this->product('BX1', 900, 'amazon', 'electronics', ['title' => 'Fast charger 25W', 'reviews' => 900]);
        $this->product('BZ1', 900, 'flipkart', 'electronics', ['title' => 'Other store item', 'reviews' => 999]);
        $this->placeOrder($u, [$phone, $case]);

        $r = $this->getJson('/api/v1/products/BP1/bought-together?limit=2')->assertOk()
            ->assertJsonPath('source', 'copurchase')->assertJsonPath('items.0.id', 'BC1')->assertJsonPath('items.0.boughtTogether', 1)
            ->assertJsonPath('items.1.id', 'BX1')->assertJsonPath('product.id', 'BP1');
        $this->assertSame($this->bdt($phone) + $this->bdt($case) + $r->json('items.1.priceBdt'), $r->json('totalBdt'));

        // No purchases yet → same-store complements (never another store).
        $this->getJson('/api/v1/products/BX1/bought-together?limit=3')->assertOk()->assertJsonPath('source', 'category')
            ->assertJsonMissing(['id' => 'BZ1']);
    }

    public function test_curated_bundle_discount_in_quote_and_order(): void
    {
        $a = $this->product('BA1', 1000);
        $b = $this->product('BB1', 2000);
        $bundle = Bundle::query()->create(['slug' => 'work-desk', 'title' => 'Work desk kit', 'discount_pct' => 5]);
        $bundle->items()->createMany([['product_id' => 'BA1', 'qty' => 1], ['product_id' => 'BB1', 'qty' => 1]]);
        $set = $this->bdt($a) + $this->bdt($b);
        $expected = (int) floor($set * 0.05);

        $this->getJson('/api/v1/bundles')->assertOk()->assertJsonPath('items.0.slug', 'work-desk');
        $this->getJson('/api/v1/bundles/work-desk')->assertOk()->assertJsonPath('bundle.discountBdt', $expected)
            ->assertJsonPath('bundle.totalBdt', $set - $expected)->assertJsonCount(2, 'bundle.items');
        $this->getJson('/api/v1/products/BA1/bundles')->assertOk()->assertJsonPath('items.0.slug', 'work-desk');

        $items = [['productId' => 'BA1', 'qty' => 1], ['productId' => 'BB1', 'qty' => 1]];
        $q = $this->postJson('/api/v1/checkout/quote', ['items' => $items])->assertOk()
            ->assertJsonPath('bundleDiscount', $expected)->assertJsonPath('bundles.0.slug', 'work-desk')->assertJsonPath('couponDiscount', 0);
        $this->assertSame($q->json('subtotal') + $q->json('shipping') - $expected, $q->json('total'));

        // Only one item → no saving.
        $this->postJson('/api/v1/checkout/quote', ['items' => [$items[0]]])->assertOk()->assertJsonPath('bundleDiscount', 0);

        $u = $this->shopper();
        $o = $this->placeOrder($u, [$a, $b]);
        $this->assertEquals($expected, $o->bundle_discount_bdt);
        $this->assertEquals($q->json('total'), $o->total_bdt);
        $this->getJson('/api/v1/orders/'.$o->id)->assertJsonPath('order.bundleDiscount', $expected);

        // Margin cap: a 50% bundle never exceeds our margin.
        $bundle->update(['discount_pct' => 50]);
        $capped = $this->postJson('/api/v1/checkout/quote', ['items' => $items])->json('bundleDiscount');
        $this->assertLessThan($set * 0.1, $capped);

        Features::set('bundles', false);
        $this->postJson('/api/v1/checkout/quote', ['items' => $items])->assertOk()->assertJsonPath('bundleDiscount', 0);
        $this->getJson('/api/v1/bundles')->assertNotFound();
    }
}
