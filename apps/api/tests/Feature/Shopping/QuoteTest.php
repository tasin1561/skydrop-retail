<?php

namespace Tests\Feature\Shopping;

use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_matches_the_pricing_rules_used_at_checkout(): void
    {
        $pricing = app(PricingService::class);
        $r = $this->getJson('/api/v1/pricing/quote?inr=1000&category=fashion&weight_kg=0.5&qty=2')->assertOk()->json();

        $unit = $pricing->priceBdt(1000, 'fashion');
        $this->assertSame($unit, $r['unit']['totalBdt']);
        $this->assertSame($unit, $r['unit']['storeBdt'] + $r['unit']['importBdt'] + $r['unit']['serviceBdt']);
        $this->assertSame($unit * 2, $r['itemsBdt']);
        $this->assertSame($pricing->shipping([['unit_bdt' => $unit, 'qty' => 2, 'weight_kg' => 0.5]]), $r['shippingBdt']);
        $this->assertSame($r['itemsBdt'] + $r['shippingBdt'], $r['totalBdt']);
        $this->assertFalse($r['weightAssumed']);
    }

    public function test_weight_defaults_to_the_category_weight_and_big_orders_ship_free(): void
    {
        $r = $this->getJson('/api/v1/pricing/quote?inr=500&category=beauty')->assertOk()->json();
        $this->assertTrue($r['weightAssumed']);
        $this->assertGreaterThan(0, $r['shippingBdt']);

        $this->assertSame(0, $this->getJson('/api/v1/pricing/quote?inr=90000&category=electronics')->assertOk()->json('shippingBdt'));
    }

    public function test_bad_input_is_rejected(): void
    {
        $this->getJson('/api/v1/pricing/quote?inr=0')->assertStatus(422);
        $this->getJson('/api/v1/pricing/quote?inr=100&category=weapons')->assertStatus(422);
        $this->getJson('/api/v1/pricing/quote?inr=100&qty=500')->assertStatus(422);
    }
}
