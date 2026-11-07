<?php

namespace Tests\Feature\System;

use App\Models\ApiLog;
use App\Models\Setting;
use App\Services\Catalog\BudgetGuard;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\ProviderChain;
use App\Support\Limits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Catalog\CatalogTestHelpers;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class SystemLimitsTest extends TestCase
{
    use CatalogTestHelpers, CommerceHelpers, RefreshDatabase;

    public function test_registry_defaults_storage_and_backed_keys(): void
    {
        $this->assertSame(20, Limits::int('orders.max_qty'));
        Limits::set('orders.max_qty', 5);
        $this->assertSame(5, Limits::get('orders.max_qty'));
        $this->assertSame(5, Setting::get('limits.orders.max_qty'));

        // Clamped to the registry range.
        Limits::set('orders.max_qty', 1000);
        $this->assertSame(99, Limits::get('orders.max_qty'));
        Limits::reset('orders.max_qty');
        $this->assertSame(20, Limits::get('orders.max_qty'));

        // Backed: writes the module's own setting, so the old admin page and RetailSettings see the same value.
        Limits::set('catalog.oxylabs_monthly_usd', 250);
        $this->assertSame(250, Setting::get('oxylabs.monthly_budget_usd'));
        $this->assertSame(250.0, app(BudgetGuard::class)->budget());

        // config: defaults follow config.
        config(['security.limits.strict_ip' => 7]);
        $this->assertSame(7, Limits::get('catalog.searches_per_min'));
    }

    public function test_cart_qty_limit_returns_422(): void
    {
        $this->setUpCommerce();
        $this->product('A1', 1000);
        Limits::set('orders.max_qty', 3);

        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 4]]])
            ->assertStatus(422)->assertJsonPath('code', 'limit_qty')
            ->assertJsonPath('message', 'You can order at most 3 of one item.')->assertJsonStructure(['errors' => ['items.0.qty']]);
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 3]]])->assertOk();

        Limits::set('orders.max_items', 1);
        $this->product('A2', 500);
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1], ['productId' => 'A2', 'qty' => 1]]])
            ->assertStatus(422)->assertJsonPath('code', 'limit_items');
    }

    public function test_max_order_value_and_partial_payment_cap(): void
    {
        $this->setUpCommerce();
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        $unit = $this->bdt($p);

        Limits::set('orders.max_value_bdt', 1000);
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1]]])
            ->assertStatus(422)->assertJsonPath('code', 'limit_order_value');
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $unit]], 'addressId' => $this->address($u)->id, 'payment' => 'bkash'])
            ->assertStatus(422)->assertJsonPath('code', 'limit_order_value');

        Limits::reset('orders.max_value_bdt');
        Limits::set('orders.max_partial_pay_bdt', 1000);
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $unit]], 'addressId' => $u->addresses()->first()->id,
            'payment' => 'advance50', 'paymentVia' => 'bkash'])
            ->assertStatus(422)->assertJsonPath('code', 'full_prepayment_required')->assertJsonStructure(['errors' => ['payment']]);
    }

    public function test_orders_per_day_and_unpaid_limits(): void
    {
        $this->setUpCommerce();
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        Limits::set('orders.max_per_day', 2);
        $this->placeOrder($u, [$p]);
        $this->placeOrder($u, [$p]);

        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]], 'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])
            ->assertStatus(422)->assertJsonPath('code', 'limit_orders_per_day');

        Limits::set('orders.max_per_day', 0); // unlimited
        Limits::set('orders.max_unpaid', 2);
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]], 'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])
            ->assertStatus(422)->assertJsonPath('code', 'limit_unpaid_orders');
    }

    public function test_min_order_value_only_blocks_placing(): void
    {
        $this->setUpCommerce();
        $u = $this->shopper();
        $p = $this->product('A1', 100);
        Limits::set('orders.min_value_bdt', 5000);
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1]]])->assertOk();
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]], 'addressId' => $this->address($u)->id, 'payment' => 'bkash'])
            ->assertStatus(422)->assertJsonPath('code', 'limit_min_order');
    }

    public function test_account_limits(): void
    {
        $this->setUpCommerce();
        $u = $this->shopper();
        $this->product('A1', 1000);
        $this->product('A2', 1000);
        Limits::set('accounts.max_wishlist', 1);
        $this->postJson('/api/v1/wishlist', ['productId' => 'A1'])->assertCreated();
        $this->postJson('/api/v1/wishlist', ['productId' => 'A1'])->assertCreated(); // same item: idempotent
        $this->postJson('/api/v1/wishlist', ['productId' => 'A2'])->assertStatus(422)->assertJsonPath('code', 'limit_wishlist');

        Limits::set('accounts.max_addresses', 1);
        $this->address($u);
        $this->postJson('/api/v1/addresses', $this->addressPayload())->assertStatus(422)->assertJsonStructure(['errors' => ['address']]);
    }

    public function test_daily_oxylabs_cap_switches_browsing_to_cache_only(): void
    {
        $this->seedCatalog();
        $this->goLive('amazon');
        config(['retail.variants.mode' => 'manual']);
        $this->fakeOxylabs();
        Limits::set('catalog.oxylabs_daily_usd', 1);
        ApiLog::query()->create(['provider' => 'oxylabs', 'type' => 'product', 'status' => 200, 'ms' => 1, 'cost_usd' => 1.5, 'created_at' => now()]);

        $guard = app(BudgetGuard::class);
        $this->assertTrue($guard->dailyCapHit());
        $this->assertLessThan(1.0, $guard->ratio()); // the monthly budget is fine; only the daily cap is hit
        $this->assertFalse($guard->allowsMetered('browse'));
        $this->assertFalse($guard->allowsBackground());
        $this->assertTrue($guard->allowsMetered('verify'));

        $this->assertNull(app(ProviderChain::class)->product('amazon', 'B0FMDLD86P'));
        Http::assertNothingSent();

        $v = app(CatalogService::class)->verify([['productId' => 'B0FMDLD86P', 'qty' => 1, 'unitBdt' => 1]]);
        $this->assertTrue($v['results'][0]['ok']); // checkout verification still goes live
        Http::assertSentCount(1);

        // Yesterday's spend does not count.
        ApiLog::query()->update(['created_at' => now()->subDay()]);
        $this->assertFalse($guard->dailyCapHit());
    }

    public function test_store_daily_fetch_cap(): void
    {
        $this->seedCatalog();
        $this->goLive('amazon');
        $this->fakeOxylabs();
        Limits::set('catalog.store_daily_fetches', 2);
        foreach ([1, 2] as $i) {
            ApiLog::query()->create(['provider' => 'oxylabs', 'type' => 'search', 'market' => 'amazon', 'status' => 200, 'ms' => 1, 'cost_usd' => 0, 'created_at' => now()]);
        }
        ApiLog::query()->create(['provider' => 'cache', 'type' => 'cache_hit', 'market' => 'amazon', 'status' => 200, 'ms' => 0, 'created_at' => now()]);
        $this->assertFalse(app(BudgetGuard::class)->allowsStore('amazon', 'browse'));
        $this->assertTrue(app(BudgetGuard::class)->allowsStore('amazon', 'verify'));
        $this->assertTrue(app(BudgetGuard::class)->allowsStore('flipkart', 'browse'));
        $this->assertNull(app(ProviderChain::class)->product('amazon', 'B0FMDLD86P'));
        Http::assertNothingSent();
    }

    public function test_public_api_per_ip_limit_and_exemptions(): void
    {
        config(['security.enforce' => true, 'security.internal_key' => 'internal-test-key']);
        Limits::set('api.per_ip_per_minute', 30);
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/features')->assertOk();
        }
        $this->getJson('/api/v1/features')->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        $this->getJson('/api/v1/health')->assertOk(); // health checks are exempt
        $this->getJson('/api/v1/features', ['X-Internal-Key' => 'internal-test-key'])->assertOk(); // Next.js server is exempt
        $this->assertGreaterThanOrEqual(1, \App\Services\System\HealthStats::total429());
    }
}
