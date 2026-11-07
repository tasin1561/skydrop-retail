<?php

namespace Tests\Feature\Money;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Jobs\Commerce\CreateSkydropConsignment;
use App\Jobs\Fraud\ScoreOrderFraud;
use App\Jobs\Messaging\SendPaymentReminders;
use App\Models\Affiliate;
use App\Models\MembershipPlan;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\ReferralReward;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\Payments\MockGateway;
use App\Services\Finance\AccountingService;
use App\Services\Finance\ProfitService;
use App\Services\Growth\AffiliateService;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

/** Gift card and Skydrop Plus purchases (orders.kind) must not pollute operations, marketing or revenue. */
class OrderKindsTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
        Bus::fake([CreateSkydropConsignment::class]);
        foreach (['wallet', 'gift_cards', 'membership', 'tracking', 'affiliate', 'referral', 'fraud_check'] as $f) {
            Features::set($f, true);
        }
        config([
            'retail.tracking.driver' => 'delivro', 'retail.tracking.purchase_on' => 'paid', 'retail.tracking.send_delivered' => true,
            'retail.tracking.delivro' => ['endpoint' => 'https://delivro.test/ingest', 'key' => 'dl-key'],
        ]);
        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    private function pay(Order $o): Order
    {
        $pay = $o->payments()->where('gateway', 'mock')->latest('id')->first();
        $this->postJson('/api/v1/webhooks/payments/mock?payment='.$pay->id, ['status' => 'success', 'signature' => MockGateway::signature($pay->id, 'success')])->assertOk();

        return $o->refresh();
    }

    private function giftCardOrder(int $amount = 1000): Order
    {
        $id = $this->postJson('/api/v1/gift-cards', ['amountBdt' => $amount, 'design' => 'classic', 'recipientName' => 'Karim',
            'recipientPhone' => '01812345678', 'payment' => 'bkash'])->assertCreated()->json('order.id');

        return Order::query()->findOrFail($id);
    }

    private function plusOrder(): Order
    {
        MembershipPlan::query()->firstOrCreate(['slug' => 'plus-monthly'], ['name' => 'Plus Monthly', 'price_bdt' => 299, 'period' => 'monthly', 'active' => true, 'benefits' => []]);
        $id = $this->postJson('/api/v1/membership', ['plan' => 'plus-monthly', 'payment' => 'nagad'])->assertCreated()->json('order.id');

        return Order::query()->findOrFail($id);
    }

    public function test_tracking_sends_purchase_for_plus_but_nothing_for_gift_cards(): void
    {
        $this->shopper();
        $gift = $this->pay($this->giftCardOrder());
        $plus = $this->pay($this->plusOrder());
        $this->assertSame('delivered', $gift->status);
        $this->assertSame('delivered', $plus->status);

        $this->assertSame(0, TrackingEvent::query()->where('order_id', $gift->id)->count());
        $events = TrackingEvent::query()->where('order_id', $plus->id)->get();
        $this->assertSame(['Purchase'], $events->pluck('event')->unique()->values()->all()); // never "Delivered"
        $payload = $events->first()->payload;
        $this->assertEquals(299, $payload['value']);
        $this->assertSame('plus-plus-monthly', $payload['items'][0]['id']);
    }

    public function test_no_fraud_check_commission_or_referral_reward_on_digital_orders(): void
    {
        $inviter = User::query()->create(['name' => 'Inviter', 'phone' => '+8801700000009']);
        $this->shopper(['referred_by' => $inviter->id]);
        $gift = $this->giftCardOrder();
        ScoreOrderFraud::dispatchSync($gift->id);
        $this->assertNull($gift->refresh()->fraud_checked_at);

        $this->pay($gift);
        $this->assertSame(0, ReferralReward::query()->count());

        $aff = Affiliate::query()->create(['user_id' => $inviter->id, 'code' => 'NADIA10', 'status' => 'approved']);
        $this->assertNull(app(AffiliateService::class)->attribute($gift, 'NADIA10'));
        $this->assertSame([0.0, 0.0], app(AffiliateService::class)->commissionFor($gift, $aff));
    }

    public function test_payment_received_wording_for_gift_card_and_plus(): void
    {
        $this->shopper();
        $gift = $this->pay($this->giftCardOrder());
        $sms = NotificationLog::query()->where('event', 'digital_payment_received')->where('order_id', $gift->id)->where('channel', 'sms')->firstOrFail();
        $this->assertStringContainsString('gift card', $sms->body);
        $this->assertStringNotContainsString('rider', $sms->body);
        $this->assertSame(0, NotificationLog::query()->where('event', 'payment_received')->where('order_id', $gift->id)->count());

        $plus = $this->pay($this->plusOrder());
        $sms = NotificationLog::query()->where('event', 'digital_payment_received')->where('order_id', $plus->id)->where('channel', 'sms')->firstOrFail();
        $this->assertStringContainsString('Skydrop Plus (Plus Monthly)', $sms->body);
    }

    public function test_payment_reminders_skip_unpaid_gift_card_and_plus_checkouts(): void
    {
        $u = $this->shopper();
        $goods = $this->placeOrder($u, [$this->product('K1', 1000)]);
        $gift = $this->giftCardOrder();
        $plus = $this->plusOrder();
        Order::query()->whereKey([$goods->id, $gift->id, $plus->id])->update(['created_at' => now()->subHours(2)]);

        (new SendPaymentReminders)->handle(app(Notifier::class));

        $this->assertGreaterThan(0, NotificationLog::query()->where('event', 'payment_pending')->where('order_id', $goods->id)->count());
        $this->assertSame(0, NotificationLog::query()->where('event', 'payment_pending')->whereIn('order_id', [$gift->id, $plus->id])->count());
    }

    public function test_admin_orders_list_hides_digital_orders_by_default(): void
    {
        $u = $this->shopper();
        $goods = $this->placeOrder($u, [$this->product('K2', 1000)]);
        $gift = $this->pay($this->giftCardOrder());
        $plus = $this->plusOrder();

        $admin = new User;
        $admin->forceFill(['name' => 'Boss', 'email' => 'boss@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();
        $this->actingAs($admin);

        Livewire::test(ListOrders::class)->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$goods])->assertCanNotSeeTableRecords([$gift, $plus])
            ->filterTable('kind', 'all')->assertCanSeeTableRecords([$goods, $gift, $plus])
            ->filterTable('kind', 'gift_card')->assertCanSeeTableRecords([$gift])->assertCanNotSeeTableRecords([$goods, $plus]);
    }

    public function test_profit_counts_plus_as_revenue_and_gift_cards_as_liability(): void
    {
        $u = $this->shopper();
        $goods = $this->placeOrder($u, [$this->product('K3', 1000)]);
        $this->pay($this->giftCardOrder(2000));
        $this->pay($this->plusOrder());

        $from = Carbon::now()->subDay();
        $to = Carbon::now();
        $k = app(ProfitService::class)->report($from, $to)['kpis'];
        $this->assertSame(1, $k['orders']); // goods only
        $this->assertEquals((float) $goods->total_bdt, $k['goods_revenue']);
        $this->assertEquals(299, $k['plus_revenue']);
        $this->assertEquals((float) $goods->total_bdt + 299, $k['revenue']);
        $this->assertEquals(2000, $k['gift_card_sales']);
        $this->assertSame(1, $k['gift_card_orders']);

        [$h, $rows] = app(AccountingService::class)->salesJournal($from, $to);
        $row = array_combine($h, $rows[0]);
        $this->assertSame(1, $row['orders']);
        $this->assertEquals((float) $goods->total_bdt, $row['net_sales_bdt']);
        $this->assertEquals(299, $row['plus_sales_bdt']);
        $this->assertEquals(2000, $row['gift_card_sales_bdt_liability']);

        $close = app(AccountingService::class)->monthClose(now()->format('Y-m'));
        $this->assertEquals(2000, $close['gift_card_sales']['amount']);
    }
}
