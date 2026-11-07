<?php

namespace Tests\Feature\Growth;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\Setting;
use App\Models\User;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Growth\AffiliateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class AffiliateTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    private function affiliate(array $attrs = []): Affiliate
    {
        $u = User::query()->create(['name' => 'Nadia Promoter', 'phone' => '+8801911111111']);

        return Affiliate::query()->create(array_merge([
            'user_id' => $u->id, 'code' => 'NADIA10', 'status' => 'approved', 'payout_method' => 'bkash', 'payout_number' => '+8801911111111',
            'approved_at' => now(),
        ], $attrs));
    }

    public function test_apply_and_dashboard(): void
    {
        $u = $this->shopper();
        $this->getJson('/api/v1/affiliate/me')->assertOk()->assertJsonPath('affiliate', null)->assertJsonPath('program.minPayoutBdt', 500);
        $this->postJson('/api/v1/affiliate/apply', ['code' => 'rahim', 'payoutMethod' => 'nagad', 'payoutNumber' => '01712345678'])
            ->assertCreated()->assertJsonPath('affiliate.code', 'RAHIM')->assertJsonPath('affiliate.status', 'pending')
            ->assertJsonPath('affiliate.payoutNumber', '+8801712345678')->assertJsonPath('stats.clicks', 0);
        // Code can be changed once.
        $this->patchJson('/api/v1/affiliate/me', ['code' => 'RAHIM2'])->assertOk()->assertJsonPath('affiliate.code', 'RAHIM2')
            ->assertJsonPath('affiliate.codeEditable', false);
        $this->patchJson('/api/v1/affiliate/me', ['code' => 'RAHIM3'])->assertUnprocessable();
        $this->assertSame('RAHIM2', Affiliate::query()->where('user_id', $u->id)->value('code'));
    }

    public function test_click_recording(): void
    {
        $a = $this->affiliate();
        $this->postJson('/api/v1/r/click', ['code' => 'nadia10', 'productId' => 'A1', 'url' => 'http://localhost:3000/p/A1?ref=NADIA10'])
            ->assertOk()->assertJsonPath('valid', true)->assertJsonPath('cookieDays', 30);
        // Same visitor + product within 30 minutes counts once.
        $this->postJson('/api/v1/r/click', ['code' => 'NADIA10', 'productId' => 'A1'])->assertOk();
        $this->postJson('/api/v1/r/click', ['code' => 'NADIA10', 'productId' => 'A2'])->assertOk();
        $this->postJson('/api/v1/r/click', ['code' => 'NOPE99'])->assertOk()->assertJsonPath('valid', false);

        $this->assertSame(2, AffiliateClick::query()->where('affiliate_id', $a->id)->count());
        $click = AffiliateClick::query()->first();
        $this->assertSame('A1', $click->product_id);
        $this->assertNotSame('127.0.0.1', $click->ip_hash);
        $this->assertSame(64, strlen($click->ip_hash));

        // Suspended affiliates do not get clicks.
        $a->update(['status' => 'suspended']);
        $this->postJson('/api/v1/r/click', ['code' => 'NADIA10', 'productId' => 'A9'])->assertJsonPath('valid', false);
    }

    public function test_commission_lifecycle_placed_delivered_approved_paid(): void
    {
        Setting::put('affiliate.rates.electronics', 5);
        Setting::put('affiliate.hold_days', 7);
        $a = $this->affiliate();
        $buyer = $this->shopper();
        $p = $this->product('A1', 1000);
        $order = $this->placeOrder($buyer, [$p], ['ref' => 'nadia10']);

        $this->assertSame($a->id, (int) $order->refresh()->affiliate_id);
        $this->assertSame('NADIA10', $order->ref_code);
        $c = AffiliateCommission::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $c->status);
        $this->assertEqualsWithDelta($order->subtotal_bdt, $c->base_bdt, 0.01); // no discounts, shipping excluded
        $this->assertEqualsWithDelta(round($order->subtotal_bdt * 0.05, 2), $c->amount_bdt, 0.01);
        $this->assertEquals(5, $c->rate);

        // Delivered: hold starts, not approved yet.
        app(OrderWorkflow::class)->transition($order, 'delivered', 'admin');
        $c->refresh();
        $this->assertSame('pending', $c->status);
        $this->assertTrue($c->approve_after->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertSame(0, app(AffiliateService::class)->approveDue());

        // After the hold.
        $this->travel(8)->days();
        $this->assertSame(1, app(AffiliateService::class)->approveDue());
        $this->assertSame('approved', $c->refresh()->status);

        // Dashboard + payout (min payout lowered so the test order qualifies).
        Setting::put('affiliate.min_payout_bdt', 50);
        Sanctum::actingAs($a->user);
        $this->getJson('/api/v1/affiliate/me')->assertOk()
            ->assertJsonPath('stats.orders', 1)->assertJsonPath('stats.approvedBdt', (int) floor($c->amount_bdt))
            ->assertJsonPath('commissions.0.order', 'SR-••••'.substr($order->id, -2));
        $r = $this->postJson('/api/v1/affiliate/payouts')->assertCreated()->assertJsonPath('payout.status', 'requested');
        $this->postJson('/api/v1/affiliate/payouts')->assertStatus(409);

        $payout = $a->payouts()->firstOrFail();
        app(AffiliateService::class)->markPaid($payout, 'BK123456');
        $this->assertSame('paid', $c->refresh()->status);
        $this->assertSame('paid', $payout->refresh()->status);
        $this->getJson('/api/v1/affiliate/me')->assertJsonPath('stats.paidBdt', (int) floor($c->amount_bdt))->assertJsonPath('stats.availableBdt', 0);
        $this->assertSame((int) round($c->amount_bdt), $r->json('payout.amountBdt'));
    }

    public function test_cancelled_order_reverses_commission(): void
    {
        $this->affiliate();
        $buyer = $this->shopper();
        $order = $this->placeOrder($buyer, [$this->product('A1', 1000)], ['ref' => 'NADIA10']);
        $this->assertSame('pending', AffiliateCommission::query()->where('order_id', $order->id)->value('status'));

        $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();
        $c = AffiliateCommission::query()->where('order_id', $order->id)->first();
        $this->assertSame('reversed', $c->status);
        $this->assertNotNull($c->reversed_at);
    }

    public function test_self_referral_is_blocked(): void
    {
        $a = $this->affiliate();
        // Same account.
        Sanctum::actingAs($a->user);
        $o1 = $this->placeOrder($a->user, [$this->product('A1', 1000)], ['ref' => 'NADIA10']);
        $this->assertNull($o1->refresh()->affiliate_id);

        // Different account, but delivering to the affiliate's phone.
        $buyer = $this->shopper(['phone' => '+8801755555555']);
        $addr = $buyer->addresses()->create(['name' => 'X', 'phone' => '+8801911111111', 'division' => 'Dhaka', 'district' => 'Dhaka',
            'area' => 'Mirpur', 'line' => 'Road 1', 'is_default' => true]);
        $o2 = $this->placeOrder($buyer, [$this->product('A2', 1000)], ['ref' => 'NADIA10', 'addressId' => $addr->id]);
        $this->assertNull($o2->refresh()->affiliate_id);

        $this->assertSame(0, AffiliateCommission::query()->count());
    }

    public function test_cap_and_discounts(): void
    {
        Setting::put('affiliate.rates.default', 50); // silly rate to hit the cap
        Setting::put('affiliate.max_per_order_bdt', 300);
        $this->affiliate();
        $buyer = $this->shopper();
        $order = $this->placeOrder($buyer, [$this->product('A1', 1000, category: 'toys')], ['ref' => 'NADIA10', 'coupon' => 'SKYDROP10']);
        $c = AffiliateCommission::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertEqualsWithDelta($order->subtotal_bdt - $order->coupon_discount_bdt, $c->base_bdt, 0.01);
        $this->assertEquals(300, $c->amount_bdt);
    }

    public function test_payout_minimum(): void
    {
        $a = $this->affiliate();
        Sanctum::actingAs($a->user);
        $this->postJson('/api/v1/affiliate/payouts')->assertUnprocessable()->assertJsonPath('code', 'below_minimum');
    }
}
