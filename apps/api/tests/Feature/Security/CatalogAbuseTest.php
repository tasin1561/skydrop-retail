<?php

namespace Tests\Feature\Security;

use App\Models\Product;
use App\Services\Catalog\CatalogService;
use App\Services\Commerce\Verify\LocalPriceVerifier;
use App\Services\Commerce\Verify\PriceVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Catalog\CatalogTestHelpers;
use Tests\TestCase;

/** Security audit 2026-11: catalog scraping and paid-provider cost abuse. */
class CatalogAbuseTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    private const BROWSER = ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130', 'Accept-Language' => 'en-BD,en;q=0.9'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.enforce' => true, 'security.internal_key' => 'internal-test-key', 'security.app_keys' => ['mobile' => 'app-secret']]);
    }

    private function product(string $id): Product
    {
        return Product::query()->create(['id' => $id, 'market' => 'amazon', 'category' => 'electronics', 'title' => "Item {$id}",
            'price_inr' => 1000, 'mrp_inr' => 1200, 'in_stock' => true]);
    }

    public function test_product_feeds_are_closed_without_a_feed_key(): void
    {
        $this->product('B0FEED0001');
        config(['retail.tracking.feed_key' => null]);
        $this->get('/api/v1/feeds/meta.csv')->assertNotFound();
        $this->get('/api/v1/feeds/google.xml')->assertNotFound();

        config(['retail.tracking.feed_key' => 'feed-key']);
        $this->get('/api/v1/feeds/meta.csv')->assertForbidden();
        $this->get('/api/v1/feeds/meta.csv?key=feed-key')->assertOk()->assertSee('B0FEED0001');
    }

    public function test_checkout_verify_needs_a_signed_client(): void
    {
        $this->app->bind(PriceVerifier::class, LocalPriceVerifier::class);
        $this->product('B0VER00001');
        $body = ['items' => [['productId' => 'B0VER00001', 'qty' => 1]]];

        $this->postJson('/api/v1/checkout/verify', $body, self::BROWSER)->assertStatus(401)->assertJsonPath('code', 'unsigned');
        $this->postJson('/api/v1/checkout/verify', $body, ['X-Internal-Key' => 'internal-test-key'])->assertOk();
    }

    public function test_verify_fetches_a_repeated_product_only_once(): void
    {
        $this->seedCatalog();
        $this->goLive('amazon');
        config(['retail.variants.mode' => 'manual']);
        $this->fakeOxylabs();

        $items = array_fill(0, 5, ['productId' => 'B0FMDLD86P', 'qty' => 1, 'unitBdt' => 1]);
        $v = app(CatalogService::class)->verify($items);
        $this->assertCount(5, $v['results']);
        Http::assertSentCount(1);
    }

    public function test_cart_quote_cannot_be_used_to_harvest_prices(): void
    {
        config(['security.bot.cart_products_per_minute' => 5]);
        foreach (range(1, 10) as $i) {
            $this->product(sprintf('B0CART%04d', $i));
        }
        $cart = fn (array $n) => ['items' => array_map(fn ($i) => ['productId' => sprintf('B0CART%04d', $i), 'qty' => 1], $n)];

        // A shopper re-quoting the same cart is fine.
        foreach (range(1, 4) as $_) {
            $this->postJson('/api/v1/checkout/quote', $cart([1, 2, 3]), self::BROWSER)->assertOk();
        }
        // Rotating through product ids gets the bot challenge, and the IP stays flagged.
        $this->postJson('/api/v1/checkout/quote', $cart([4, 5, 6, 7]), self::BROWSER)->assertStatus(403)->assertJsonPath('code', 'challenge');
        $this->postJson('/api/v1/checkout/quote', $cart([1]), self::BROWSER)->assertStatus(403);
        // The Next.js server is not counted.
        $this->postJson('/api/v1/checkout/quote', $cart([8, 9, 10]), ['X-Internal-Key' => 'internal-test-key'])->assertOk();
    }
}
