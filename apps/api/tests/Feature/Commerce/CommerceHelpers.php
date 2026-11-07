<?php

namespace Tests\Feature\Commerce;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\Sms\LogSms;
use App\Services\Commerce\Verify\LocalPriceVerifier;
use App\Services\Commerce\Verify\PriceVerifier;
use App\Services\PricingService;
use Database\Seeders\CouponSeeder;
use Laravel\Sanctum\Sanctum;

trait CommerceHelpers
{
    protected function setUpCommerce(): void
    {
        config([
            'retail.otp.driver' => 'log', 'retail.sms.driver' => 'log', 'retail.payments.mode' => 'mock',
            'retail.skydrop.mode' => 'mock', 'retail.skydrop.webhook_secret' => 'test-secret',
            'retail.web_url' => 'http://localhost:3000', 'retail.pricing.fx_inr_bdt' => 1.42,
        ]);
        LogSms::$sent = [];
        // Tests use the stored catalog price as the "live" price.
        $this->app->bind(PriceVerifier::class, LocalPriceVerifier::class);
        $this->seed(CouponSeeder::class);
    }

    protected function product(string $id = 'B0TEST0001', float $inr = 1000, string $market = 'amazon', string $category = 'electronics', array $extra = []): Product
    {
        return Product::query()->create(array_merge([
            'id' => $id, 'market' => $market, 'category' => $category, 'title' => "Test product {$id}", 'brand' => 'Acme',
            'price_inr' => $inr, 'mrp_inr' => $inr * 1.2, 'image' => null, 'in_stock' => true,
        ], $extra));
    }

    protected function bdt(Product $p): int
    {
        return app(PricingService::class)->priceBdt((float) $p->price_inr, $p->category);
    }

    protected function shopper(array $attrs = []): User
    {
        $u = User::query()->create(array_merge(['name' => 'Rahim Uddin', 'phone' => '+8801712345678'], $attrs));
        Sanctum::actingAs($u);

        return $u;
    }

    protected function address(User $u): Address
    {
        return $u->addresses()->create(['name' => 'Rahim', 'phone' => '+8801712345678', 'division' => 'Dhaka', 'district' => 'Dhaka',
            'area' => 'Mirpur', 'line' => 'House 1, Road 2', 'is_default' => true]);
    }

    protected function addressPayload(array $o = []): array
    {
        return array_merge(['name' => 'Karim', 'phone' => '01812345678', 'division' => 'Chattogram', 'district' => 'Cumilla', 'area' => 'Kandirpar', 'line' => 'Road 5'], $o);
    }

    /** Places an order through the service (mock payment, not yet paid). */
    protected function placeOrder(User $u, array $products, array $extra = []): Order
    {
        $a = $u->addresses()->first() ?? $this->address($u);
        $r = app(OrderService::class)->place($u, array_merge([
            'items' => array_map(fn (Product $p) => ['productId' => $p->id, 'qty' => 1, 'unitBdt' => $this->bdt($p)], $products),
            'addressId' => $a->id, 'payment' => 'bkash',
        ], $extra));

        return $r['order'];
    }
}
