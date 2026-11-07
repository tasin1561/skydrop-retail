<?php

namespace Tests\Feature\Shopping;

use App\Services\PricingService;
use App\Support\Features;
use App\Support\ProductJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceHistoryTest extends TestCase
{
    use RefreshDatabase, ShoppingHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopping();
    }

    public function test_series_in_bdt_with_stats_and_badges(): void
    {
        $p = $this->product('PH1', 900);
        $this->history($p, [40 => 1000, 20 => 1100, 10 => 1050, 3 => 950]);
        $pricing = app(PricingService::class);

        $r = $this->getJson('/api/v1/products/PH1/price-history?days=30')->assertOk()
            ->assertJsonPath('productId', 'PH1')->assertJsonPath('days', 30)->assertJsonPath('currency', 'BDT');
        $points = $r->json('points');
        $this->assertCount(30, $points); // carried forward from the price before the window
        $this->assertSame($pricing->priceBdt(1000, 'electronics'), $points[0]['bdt']);
        $this->assertSame($pricing->priceBdt(900, 'electronics'), end($points)['bdt']);
        $this->assertEquals(900, end($points)['inr']);
        $r->assertJsonPath('lowestBdt', $pricing->priceBdt(900, 'electronics'))
            ->assertJsonPath('highestBdt', $pricing->priceBdt(1100, 'electronics'))
            ->assertJsonPath('currentBdt', $pricing->priceBdt(900, 'electronics'))
            ->assertJsonPath('positionLabel', 'lowest');
        $types = array_column($r->json('badges'), 'type');
        $this->assertSame(['lowest', 'drop'], $types);
        $this->assertSame($pricing->priceBdt(1050, 'electronics') - $pricing->priceBdt(900, 'electronics'), $r->json('badges.1.dropBdt'));

        // 365-day window starts at the first known price; unsupported periods fall back to 30.
        $this->assertSame(41, count($this->getJson('/api/v1/products/PH1/price-history?days=365')->json('points')));
        $this->getJson('/api/v1/products/PH1/price-history?days=7')->assertJsonPath('days', 30);
        $this->getJson('/api/v1/products/NOPE1/price-history')->assertNotFound();
    }

    public function test_no_history_gives_single_point_and_no_insight(): void
    {
        $p = $this->product('PH2', 500);
        $this->getJson('/api/v1/products/PH2/price-history')->assertOk()->assertJsonCount(1, 'points')
            ->assertJsonPath('positionLabel', 'steady')->assertJsonPath('badges', []);
        $this->assertNull(ProductJson::one($p)['priceInsight']);
    }

    public function test_card_price_insight_and_feature_switch(): void
    {
        $p = $this->product('PH3', 800);
        $this->history($p, [25 => 1000, 12 => 1000]);
        $this->assertSame('lowest', ProductJson::one($p)['priceInsight']['type']);

        Features::set('price_history', false);
        $this->getJson('/api/v1/products/PH3/price-history')->assertNotFound()->assertJsonPath('error', 'feature_disabled');
        $p2 = $this->product('PH4', 800);
        $this->history($p2, [25 => 1000]);
        $this->assertNull(ProductJson::one($p2)['priceInsight']);
    }
}
