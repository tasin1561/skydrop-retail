<?php

namespace Tests\Feature\Commerce;

use App\Models\PointsLedger;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\PointsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointsTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    public function test_points_are_earned_on_delivery_only(): void
    {
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        $o = $this->placeOrder($u, [$p]);
        $expected = intdiv($this->bdt($p), 100);
        $this->assertSame($expected, $o->points_to_earn);
        $this->getJson('/api/v1/points')->assertOk()->assertJsonPath('balance', 0)->assertJsonPath('pending', $expected);

        app(OrderWorkflow::class)->transition($o, 'delivered', 'admin');
        $this->getJson('/api/v1/points')->assertOk()->assertJsonPath('balance', $expected)->assertJsonPath('pending', 0)
            ->assertJsonPath('history.0.type', 'earn')->assertJsonPath('history.0.orderId', $o->id);
        $this->getJson("/api/v1/orders/{$o->id}")->assertJsonPath('order.pointsEarned', $expected);

        // Not credited twice.
        app(PointsService::class)->earn($o->refresh());
        $this->assertSame($expected, $u->refresh()->points_balance);
    }

    public function test_redeem_is_capped_at_twenty_percent_in_blocks(): void
    {
        $u = $this->shopper();
        app(PointsService::class)->adjust($u, 5000, 'test');
        $p = $this->product('A1', 1000); // ~৳1,830 => 20% = ৳366 => 7 blocks of ৳50 = 700 points
        $sub = $this->bdt($p);
        $maxBlocks = (int) floor($sub * 0.2 / 50);

        $r = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1]], 'redeemPoints' => 5000])->assertOk();
        $this->assertSame($maxBlocks * 100, $r->json('pointsRedeemed'));
        $this->assertSame($maxBlocks * 50, $r->json('pointsDiscount'));
        $this->assertLessThanOrEqual($sub * 0.2, $r->json('pointsDiscount'));

        // Rounded down to whole blocks.
        $r = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1]], 'redeemPoints' => 250])->assertOk();
        $this->assertSame(200, $r->json('pointsRedeemed'));
        $this->assertSame(100, $r->json('pointsDiscount'));
    }

    public function test_redeemed_points_are_refunded_on_cancel(): void
    {
        $u = $this->shopper();
        app(PointsService::class)->adjust($u, 300, 'test');
        $o = $this->placeOrder($u->refresh(), [$this->product('A1', 1000)], ['redeemPoints' => 300]);
        $this->assertSame(300, $o->points_redeemed);
        $this->assertEquals(150, $o->points_discount_bdt);
        $this->assertSame(0, $u->refresh()->points_balance);

        $this->postJson("/api/v1/orders/{$o->id}/cancel")->assertOk()->assertJsonPath('order.pointsRedeemed', 300);
        $this->assertSame(300, $u->refresh()->points_balance);
        $this->assertSame(1, PointsLedger::query()->where('order_id', $o->id)->where('type', 'refund')->count());
    }

    public function test_points_expire(): void
    {
        $u = $this->shopper();
        $svc = app(PointsService::class);
        $svc->adjust($u, 200, 'old');
        PointsLedger::query()->update(['expires_at' => now()->subDay()]);
        $svc->adjust($u, -50, 'used some');
        $this->assertSame(150, $u->refresh()->points_balance);

        $this->assertSame(150, $svc->expire());
        $this->assertSame(0, $u->refresh()->points_balance);
        $this->assertSame(0, $svc->expire());
    }
}
