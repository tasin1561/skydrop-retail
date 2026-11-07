<?php

namespace Tests\Feature\Commerce;

use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    public function test_groups_by_marketplace_with_shipping(): void
    {
        $a = $this->product('A1', 1000, 'amazon');
        $f = $this->product('F1', 500, 'flipkart', 'fashion');
        $r = $this->postJson('/api/v1/checkout/quote', ['items' => [
            ['productId' => 'F1', 'qty' => 2, 'unitBdt' => 1], ['productId' => 'A1', 'qty' => 1],
        ]])->assertOk();

        $this->assertSame(['amazon', 'flipkart'], array_column($r->json('groups'), 'market'));
        $sub = $this->bdt($a) + 2 * $this->bdt($f);
        $this->assertSame($sub, $r->json('subtotal'));
        $pricing = app(PricingService::class);
        $ship = $pricing->shipping([['unit_bdt' => $this->bdt($a), 'qty' => 1, 'weight_kg' => 0.4]])
            + $pricing->shipping([['unit_bdt' => $this->bdt($f), 'qty' => 2, 'weight_kg' => 0.6]]);
        $this->assertSame($ship, $r->json('shipping'));
        $this->assertSame($sub + $ship, $r->json('total'));
        $this->assertTrue($r->json('items.0.changed')); // client sent unitBdt=1 for F1
        $this->assertFalse($r->json('items.1.changed'));
    }

    public function test_skydrop10_is_ten_percent_capped_at_1000(): void
    {
        $p = $this->product('A1', 1000);
        $r = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1]], 'coupon' => 'skydrop10'])->assertOk();
        $this->assertSame((int) round($this->bdt($p) * 0.1), $r->json('discount'));
        $this->assertSame('percent', $r->json('coupon.kind'));

        $this->product('BIG', 20000);
        $r = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'BIG', 'qty' => 1]], 'coupon' => 'SKYDROP10'])->assertOk();
        $this->assertSame(1000, $r->json('discount'));
        $this->assertSame(0, $r->json('shipping')); // over the free-shipping threshold
    }

    public function test_freeship_removes_all_shipping(): void
    {
        $this->product('A1', 1000);
        $this->product('F1', 1000, 'flipkart');
        $r = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1], ['productId' => 'F1', 'qty' => 1]], 'coupon' => 'FREESHIP'])->assertOk();
        $this->assertGreaterThan(0, $r->json('shipping'));
        $this->assertSame($r->json('shipping'), $r->json('discount'));
        $this->assertSame($r->json('subtotal'), $r->json('total'));
    }

    public function test_unknown_coupon_is_rejected(): void
    {
        $this->product('A1', 1000);
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1]], 'coupon' => 'NOPE'])
            ->assertStatus(422)->assertJsonValidationErrors('coupon');
    }

    public function test_verify_returns_documented_shape(): void
    {
        $p = $this->product('A1', 1000);
        $this->postJson('/api/v1/checkout/verify', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]]])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('results.0.freshBdt', $this->bdt($p))
            ->assertJsonPath('results.0.changed', false)->assertJsonStructure(['checkedAt', 'results' => [['productId', 'ok', 'freshBdt', 'changed', 'deliverable']]]);
    }
}
