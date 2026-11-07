<?php

namespace Tests\Feature\Money;

use App\Models\GiftCard;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Payments\MockGateway;
use App\Services\Commerce\Payments\SslCommerzGateway;
use App\Services\Commerce\PointsService;
use App\Services\Money\MembershipService;
use App\Services\Money\MoneySettings;
use App\Services\Money\WalletService;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
        Features::set('wallet', true);
        Features::set('gift_cards', true);
        Features::set('membership', true);
        Features::set('pay.emi', true);
    }

    private function wallet(): WalletService
    {
        return app(WalletService::class);
    }

    private function pay(Order $o): void
    {
        $pay = $o->payments()->where('gateway', 'mock')->latest('id')->first();
        $this->postJson('/api/v1/webhooks/payments/mock?payment='.$pay->id, ['status' => 'success', 'signature' => MockGateway::signature($pay->id, 'success')])->assertOk();
    }

    private function plan(array $benefits = [], array $attrs = []): MembershipPlan
    {
        return MembershipPlan::query()->create(array_merge(['name' => 'Plus Monthly', 'slug' => 'plus-monthly', 'price_bdt' => 299,
            'period' => 'monthly', 'active' => true, 'highlight' => true, 'benefits' => $benefits], $attrs));
    }

    // ---- wallet ----

    public function test_wallet_never_goes_negative(): void
    {
        $u = $this->shopper();
        $this->wallet()->credit($u, 500, 'admin', 'Test');
        $this->wallet()->debit($u, 300, 'order_payment');
        try {
            $this->wallet()->debit($u, 201, 'order_payment');
            $this->fail('Overdraft allowed');
        } catch (CommerceException $e) {
            $this->assertStringContainsString('not enough', $e->getMessage());
        }
        $this->assertEquals(200, $this->wallet()->balance($u));
        $this->assertEquals(200, $this->wallet()->ledgerBalance($u));

        $this->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('balanceBdt', 200)->assertJsonCount(2, 'history');
    }

    public function test_wallet_credit_lots_expire_fifo(): void
    {
        $u = $this->shopper();
        $this->wallet()->credit($u, 100, 'promo', 'Expiring', null, now()->addDay());
        $this->wallet()->credit($u, 100, 'admin', 'Forever');
        $this->wallet()->debit($u, 60, 'order_payment'); // taken from the expiring lot first
        $this->travel(2)->days();
        $this->assertEquals(40, $this->wallet()->expire());
        $this->assertEquals(100, $this->wallet()->balance($u->fresh()));
    }

    public function test_partial_wallet_payment_then_gateway_for_the_rest(): void
    {
        $u = $this->shopper();
        $p = $this->product('W1', 1000);
        $this->wallet()->credit($u, 500, 'admin');

        $quote = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'W1', 'qty' => 1]], 'useWallet' => true])->assertOk()->json();
        $this->assertSame(500, $quote['walletApplied']);
        $this->assertSame($quote['total'] - 500, $quote['payNow']);

        $o = $this->placeOrder($u, [$p], ['useWallet' => true]);
        $this->assertEquals(500, $o->paid_bdt);
        $this->assertEquals(500, $o->wallet_bdt);
        $this->assertSame('pending', $o->payment_status);
        $this->assertEquals(0, $this->wallet()->balance($u));
        $gw = $o->payments()->where('gateway', 'mock')->first();
        $this->assertEquals($o->total_bdt - 500, $gw->amount_bdt);

        $this->pay($o);
        $o->refresh();
        $this->assertSame('paid', $o->payment_status);
        $this->assertSame('verified', $o->status);
        $this->assertEquals($o->total_bdt, $o->paid_bdt);
    }

    public function test_full_wallet_payment_needs_no_gateway(): void
    {
        $u = $this->shopper();
        $p = $this->product('W2', 300);
        $this->wallet()->credit($u, 5000, 'admin');
        $o = $this->placeOrder($u, [$p], ['useWallet' => true]);
        $this->assertSame('paid', $o->payment_status);
        $this->assertSame('verified', $o->status);
        $this->assertSame(0, $o->payments()->where('gateway', 'mock')->count());
        $this->assertEquals(5000 - $o->total_bdt, $this->wallet()->balance($u));
    }

    public function test_wallet_covers_the_advance_of_advance50(): void
    {
        $u = $this->shopper();
        $p = $this->product('W3', 1000);
        $this->wallet()->credit($u, 10000, 'admin');
        $o = $this->placeOrder($u, [$p], ['payment' => 'advance50', 'useWallet' => (int) ceil($this->bdt($p) / 2) + 500]);
        $this->assertSame('partial', $o->payment_status);
        $this->assertTrue($o->balanceBdt() > 0);
    }

    // ---- refunds ----

    public function test_cancel_refunds_to_wallet_with_bonus_when_chosen(): void
    {
        MoneySettings::put('wallet.refund_bonus_pct', 2);
        $u = $this->shopper();
        $this->wallet()->credit($u, 200, 'admin');
        $o = $this->placeOrder($u, [$this->product('R1', 1000)], ['useWallet' => true]);
        $this->pay($o);
        $paidByGateway = (float) $o->refresh()->total_bdt - 200;

        $this->postJson("/api/v1/orders/{$o->id}/cancel", ['refundTo' => 'wallet'])->assertOk()->assertJsonPath('order.paymentStatus', 'refunded');
        // wallet part back as-is, gateway part + 2% bonus
        $this->assertEqualsWithDelta(200 + $paidByGateway * 1.02, $this->wallet()->balance($u), 0.01);
        $this->assertSame(0, $o->payments()->where('status', 'paid')->count());
    }

    public function test_cancel_refunds_wallet_part_to_wallet_and_rest_to_gateway_by_default(): void
    {
        $u = $this->shopper();
        $this->wallet()->credit($u, 200, 'admin');
        $o = $this->placeOrder($u, [$this->product('R2', 1000)], ['useWallet' => true]);
        $this->pay($o);
        app(OrderWorkflow::class)->cancel($o->refresh(), 'admin', 'test');
        $this->assertEquals(200, $this->wallet()->balance($u));
        $gw = $o->payments()->where('gateway', 'mock')->first();
        $this->assertSame('refunded', $gw->status);
        $this->assertStringStartsWith('MOCKREF-', $gw->refund_ref);
    }

    // ---- gift cards ----

    public function test_gift_card_purchase_then_redeem(): void
    {
        $buyer = $this->shopper();
        $this->getJson('/api/v1/gift-cards/config')->assertOk()->assertJsonPath('presets', [500, 1000, 2000, 5000]);
        $r = $this->postJson('/api/v1/gift-cards', ['amountBdt' => 1000, 'design' => 'eid', 'recipientName' => 'Karim',
            'recipientPhone' => '01812345678', 'message' => 'Eid Mubarak!', 'payment' => 'bkash'])->assertCreated()
            ->assertJsonPath('payment.status', 'redirect')->json();
        $order = Order::query()->findOrFail($r['order']['id']);
        $this->assertSame('gift_card', $order->kind);
        $card = GiftCard::query()->firstOrFail();
        $this->assertSame('pending', $card->status);
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'items'); // not listed as a shop order

        $this->pay($order);
        $card->refresh();
        $this->assertSame('active', $card->status);
        $this->assertNotNull($card->delivered_at);
        $this->assertSame('delivered', $order->refresh()->status);
        $this->getJson('/api/v1/gift-cards')->assertOk()->assertJsonPath('items.0.code', $card->formatted());

        // Recipient redeems (lower-case, spaces allowed).
        $friend = $this->shopper(['phone' => '+8801812345678', 'name' => 'Karim']);
        $this->postJson('/api/v1/gift-cards/redeem', ['code' => strtolower(implode(' ', str_split($card->code, 4)))])->assertOk()
            ->assertJsonPath('amountBdt', 1000)->assertJsonPath('balanceBdt', 1000);
        $this->assertEquals(1000, $this->wallet()->balance($friend));
        $this->postJson('/api/v1/gift-cards/redeem', ['code' => $card->code])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertSame('redeemed', $card->refresh()->status);
    }

    public function test_gift_cards_switch_off(): void
    {
        $this->shopper();
        Features::set('gift_cards', false);
        $this->getJson('/api/v1/gift-cards/config')->assertNotFound()->assertJsonPath('error', 'feature_disabled');
    }

    // ---- membership ----

    public function test_membership_benefits_apply_in_quote_and_order(): void
    {
        $plan = $this->plan(['free_shipping' => true, 'extra_discount_pct' => 5, 'extra_discount_cap_bdt' => 50, 'points_multiplier' => 2]);
        $u = $this->shopper();
        $p = $this->product('M1', 3000);
        $items = ['items' => [['productId' => 'M1', 'qty' => 1]]];

        // Non-member: nudge.
        $before = $this->postJson('/api/v1/checkout/quote', $items)->assertOk()->json();
        $this->assertNull($before['membership']);
        $this->assertGreaterThan(0, $before['plusPreview']['savedBdt']);

        app(MembershipService::class)->extendOrStart($u, $plan, 1, ['source' => 'test']);
        $q = $this->postJson('/api/v1/checkout/quote', $items)->assertOk()->json();
        $this->assertSame('plus-monthly', $q['membership']['plan']['slug']);
        $ship = $before['shipping'];
        $this->assertSame($ship + 50, $q['membershipDiscount']); // free shipping + capped 5%
        $this->assertSame($before['total'] - $ship - 50, $q['total']);
        $this->assertSame(app(PointsService::class)->toEarn($before['subtotal'] - 50) * 2, $q['pointsToEarn']);

        $o = $this->placeOrder($u, [$p]);
        $this->assertEquals($q['total'], $o->total_bdt);
        $this->assertEquals($ship + 50, $o->membership_discount_bdt);
        $this->getJson('/api/v1/membership')->assertOk()->assertJsonPath('membership.plan.slug', 'plus-monthly');
    }

    public function test_membership_purchase_activates_and_monthly_credit_runs(): void
    {
        $plan = $this->plan(['monthly_wallet_credit_bdt' => 100]);
        $u = $this->shopper();
        $this->getJson('/api/v1/membership/plans')->assertOk()->assertJsonPath('items.0.slug', 'plus-monthly');
        $r = $this->postJson('/api/v1/membership', ['plan' => 'plus-monthly', 'payment' => 'nagad'])->assertCreated()->json();
        $this->assertNull(app(MembershipService::class)->current($u));
        $this->pay(Order::query()->findOrFail($r['order']['id']));

        $m = app(MembershipService::class)->current($u);
        $this->assertNotNull($m);
        $this->assertTrue($m->renews_at->between(now()->addDays(27), now()->addDays(32)));
        $this->assertSame(1, app(MembershipService::class)->creditMonthly());
        $this->assertSame(0, app(MembershipService::class)->creditMonthly()); // once a month
        $this->assertEquals(100, $this->wallet()->balance($u));

        // Renewal stacks on the paid-through date.
        $r2 = $this->postJson('/api/v1/membership', ['plan' => $plan->id, 'payment' => 'bkash'])->assertCreated()->json();
        $this->pay(Order::query()->findOrFail($r2['order']['id']));
        $this->assertSame(1, Membership::query()->count());
        $this->assertTrue(Membership::query()->first()->renews_at->isAfter(now()->addDays(55)));
    }

    public function test_membership_switch_off_removes_benefits(): void
    {
        $plan = $this->plan(['free_shipping' => true]);
        $u = $this->shopper();
        $this->product('M2', 1000);
        app(MembershipService::class)->extendOrStart($u, $plan, 1, ['source' => 'test']);
        Features::set('membership', false);
        $q = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'M2', 'qty' => 1]]])->assertOk()->json();
        $this->assertSame(0, $q['membershipDiscount']);
        $this->assertNull($q['plusPreview']);
    }

    // ---- EMI ----

    public function test_emi_eligibility_and_order(): void
    {
        $u = $this->shopper();
        $this->product('E1', 1000);
        $big = $this->product('E2', 6000);

        $small = $this->getJson('/api/v1/emi?amount=3000')->assertOk()->json();
        $this->assertFalse($small['eligible']);
        $this->assertNull($small['fromMonthlyBdt']);
        $ok = $this->getJson('/api/v1/emi?amount=12000')->assertOk()->json();
        $this->assertTrue($ok['eligible']);
        $this->assertSame([3, 6, 9, 12], $ok['tenures']);
        $this->assertSame(1080, $ok['fromMonthlyBdt']); // cheapest: 12 months at 8% (BRAC) = 12960 / 12
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'E1', 'qty' => 1]], 'payment' => 'card_emi', 'emiTenure' => 6])
            ->assertOk()->assertJsonPath('emi.eligible', false)->assertJsonPath('emiCharge', 0);

        $q = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'E2', 'qty' => 1]], 'payment' => 'card_emi', 'emiTenure' => 6, 'emiBank' => 'brac'])
            ->assertOk()->json();
        $base = $q['total'] - $q['emiCharge'];
        $this->assertSame((int) ceil($base * 0.045), $q['emiCharge']);
        $this->assertSame((int) ceil($q['total'] / 6), $q['emi']['monthlyBdt']);

        $o = $this->placeOrder($u, [$big], ['payment' => 'card_emi', 'emiTenure' => 6, 'emiBank' => 'brac']);
        $this->assertSame('card_emi', $o->payment_method);
        $this->assertEquals($q['total'], $o->total_bdt);
        $this->assertSame(6, $o->emi['tenure']);
        $this->assertSame(['emi_option' => 1, 'emi_max_inst_option' => 12, 'emi_selected_inst' => 6, 'emi_allow_only' => 1], SslCommerzGateway::emiParams($o));

        // Below the minimum the order is refused.
        $this->expectException(CommerceException::class);
        $this->placeOrder($u, [$this->product('E3', 1000)], ['payment' => 'card_emi', 'emiTenure' => 6]);
    }

    public function test_emi_switch_off(): void
    {
        Features::set('pay.emi', false);
        $this->getJson('/api/v1/emi?amount=20000')->assertOk()->assertJsonPath('enabled', false)->assertJsonPath('eligible', false);
    }

    public function test_admin_pages_render(): void
    {
        $admin = new User;
        $admin->forceFill(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();
        $this->plan();
        $this->actingAs($admin);
        foreach (['/admin/wallet-ledgers', '/admin/gift-cards', '/admin/membership-plans', '/admin/membership-plans/create', '/admin/memberships', '/admin/money-settings'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
