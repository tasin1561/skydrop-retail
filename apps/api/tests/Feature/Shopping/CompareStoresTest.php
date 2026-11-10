<?php

namespace Tests\Feature\Shopping;

use App\Jobs\Shopping\MatchProducts;
use App\Models\ProductDetail;
use App\Models\ProductMatch;
use App\Services\Shopping\ProductMatcher;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareStoresTest extends TestCase
{
    use RefreshDatabase, ShoppingHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopping();
    }

    public function test_matcher_uses_gtin_model_and_title(): void
    {
        $a = $this->product('CA1', 30000, 'amazon', 'electronics', ['brand' => 'Sony', 'title' => 'Sony WH-1000XM5 Wireless Noise Cancelling Headphones, Black']);
        $f = $this->product('CF1', 28000, 'flipkart', 'electronics', ['brand' => 'SONY', 'title' => 'SONY WH1000XM5 Bluetooth Headset (Black, On the Ear)']);
        $this->product('CA2', 2000, 'amazon', 'electronics', ['brand' => 'Sony', 'title' => 'Sony MDR-ZX110 Wired On-Ear Headphones']);
        // Same model, different capacity: not the same product.
        $this->product('CP1', 20000, 'amazon', 'electronics', ['brand' => 'Samsung', 'title' => 'Samsung Galaxy A15 5G (SM-A156E) 128GB']);
        $this->product('CP2', 22000, 'flipkart', 'electronics', ['brand' => 'Samsung', 'title' => 'SAMSUNG Galaxy A15 5G SM-A156E 256GB']);
        // Barcodes win over titles.
        $g1 = $this->product('CG1', 300, 'amazon', 'grocery', ['brand' => 'Dabur', 'title' => 'Dabur Honey 500g']);
        $g2 = $this->product('CG2', 280, 'flipkart', 'grocery', ['brand' => 'Dabur', 'title' => 'Honey - 100% Pure, 500 g Bottle']);
        ProductDetail::query()->create(['product_id' => 'CG1', 'specs' => ['EAN' => '08901207001234']]);
        ProductDetail::query()->create(['product_id' => 'CG2', 'specs' => ['GTIN' => '8901207001234']]);

        (new MatchProducts)->handle(app(ProductMatcher::class));

        $this->assertDatabaseHas('product_matches', ['product_id' => 'CA1', 'match_id' => 'CF1', 'method' => 'model', 'status' => 'pending']);
        $this->assertDatabaseHas('product_matches', ['product_id' => 'CG1', 'match_id' => 'CG2', 'method' => 'gtin']);
        $this->assertDatabaseMissing('product_matches', ['product_id' => 'CP1', 'match_id' => 'CP2']);
        $this->assertDatabaseMissing('product_matches', ['product_id' => 'CA1', 'match_id' => 'CA2']);
        $this->assertLessThan(0.3, ProductMatcher::similarity('airdopes earbuds', 'cotton kurta'));
        $this->assertGreaterThan(0.55, ProductMatcher::similarity(ProductMatcher::normTitle('boAt Airdopes 141 Bluetooth Earbuds', 'boAt'), ProductMatcher::normTitle('boAt Airdopes 141 TWS Earbuds', 'boAt')));

        // Re-running never overrides an admin decision.
        ProductMatch::query()->where('product_id', 'CG1')->update(['status' => 'rejected']);
        app(ProductMatcher::class)->run();
        $this->assertDatabaseHas('product_matches', ['product_id' => 'CG1', 'status' => 'rejected']);
        $this->assertSame(0, app(ProductMatcher::class)->forProduct($a));
    }

    public function test_compare_endpoint_and_checkout_suggestion(): void
    {
        $a = $this->product('CA1', 30000, 'amazon', 'electronics', ['brand' => 'Sony', 'title' => 'Sony WH-1000XM5 Headphones', 'prime' => true]);
        $f = $this->product('CF1', 28000, 'flipkart', 'electronics', ['brand' => 'Sony', 'title' => 'SONY WH1000XM5 Headset']);
        app(ProductMatcher::class)->run();

        $r = $this->getJson('/api/v1/products/CA1/compare')->assertOk()->assertJsonCount(2, 'items')
            ->assertJsonPath('bestId', 'CF1')->assertJsonPath('items.0.productId', 'CF1')->assertJsonPath('items.0.best', true)
            ->assertJsonPath('items.0.market', 'flipkart')->assertJsonPath('items.1.current', true)
            ->assertJsonPath('items.1.delivery.min', 6)->assertJsonPath('items.0.product.id', 'CF1');
        $this->assertSame($this->bdt($a) - $this->bdt($f), $r->json('savingBdt'));

        $this->postJson('/api/v1/checkout/alternatives', ['items' => [['productId' => 'CA1', 'qty' => 2]]])->assertOk()
            ->assertJsonPath('items.0.alternativeId', 'CF1')->assertJsonPath('items.0.savingBdt', ($this->bdt($a) - $this->bdt($f)) * 2);
        // Cheapest listing: nothing to suggest.
        $this->postJson('/api/v1/checkout/alternatives', ['items' => [['productId' => 'CF1', 'qty' => 1]]])->assertOk()->assertJsonCount(0, 'items');

        // Rejected → hidden.
        ProductMatch::query()->update(['status' => 'rejected']);
        $this->getJson('/api/v1/products/CA1/compare')->assertOk()->assertJsonCount(0, 'items')->assertJsonPath('bestId', null);

        Features::set('compare_stores', false);
        $this->getJson('/api/v1/products/CA1/compare')->assertNotFound();
    }
}
