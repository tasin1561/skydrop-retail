<?php

namespace Tests\Feature\Ops;

use App\Filament\Pages\FraudCheck;
use App\Filament\Resources\BlockedEntries\BlockedEntryResource;
use App\Models\CourierCheck;
use App\Models\Order;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Payments\PaymentService;
use App\Services\Fraud\FraudService;
use App\Services\Security\Blocklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FraudTest extends TestCase
{
    use OpsTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'finance.fraud.couriers.steadfast.enabled' => true,
            'finance.fraud.couriers.steadfast.api_key' => 'sf-key',
            'finance.fraud.couriers.steadfast.secret_key' => 'sf-secret',
        ]);
    }

    private function fakeSteadfast(int $delivered, int $cancelled): void
    {
        Http::fake(['portal.packzy.com/*' => Http::response(['status' => 200, 'total_parcels' => $delivered + $cancelled,
            'total_delivered' => $delivered, 'total_cancelled' => $cancelled])]);
    }

    public function test_bad_courier_history_scores_high_and_is_cached(): void
    {
        $this->fakeSteadfast(1, 9);
        $r = app(FraudService::class)->score('01711000000');

        $this->assertSame(40, $r['score']);
        $this->assertSame('medium', $r['level']);
        $this->assertSame(10, $r['courier_total']);
        $this->assertStringContainsString('courier success 10%', implode(' ', $r['reasons']));
        Http::assertSent(fn ($req) => $req->hasHeader('Api-Key', 'sf-key') && $req->hasHeader('Secret-Key', 'sf-secret')
            && str_ends_with($req->url(), '/fraud_check/01711000000'));

        // Cached for 7 days: no second HTTP call.
        app(FraudService::class)->score('+8801711000000');
        Http::assertSentCount(1);
        $this->assertSame(1, CourierCheck::query()->count());
    }

    public function test_good_history_is_low_risk(): void
    {
        $this->fakeSteadfast(20, 0);
        $r = app(FraudService::class)->score('01811000000');
        $this->assertSame('low', $r['level']);
        $this->assertSame(0, $r['score']);
    }

    public function test_courier_error_is_recorded_not_thrown(): void
    {
        Http::fake(['portal.packzy.com/*' => Http::response('nope', 500)]);
        $r = app(FraudService::class)->score('01911000000');
        $this->assertArrayHasKey('error', $r['couriers']['steadfast']);
        $this->assertSame('low', $r['level']);
    }

    public function test_high_risk_order_needs_review_requires_full_prepay_and_is_not_auto_verified(): void
    {
        $this->fakeSteadfast(0, 6);
        Blocklist::block('phone', '01722000000', 'test');
        $user = $this->customer('+8801722000000');
        // Placed (queued job runs synchronously in tests on create).
        $order = $this->makeOrder(['status' => 'placed', 'payment_status' => 'pending', 'paid_bdt' => 0, 'payment_method' => 'advance50', 'payment_via' => 'bkash'], [], $user);

        $this->assertTrue((bool) $order->fraud_review);
        $this->assertSame('high', $order->fraud_level);
        $this->assertNotEmpty($order->fraud_reasons);

        // Full prepayment instead of the 50% advance.
        $this->assertEquals($order->total_bdt, app(PaymentService::class)->amountNow($order));

        // Payment arrives: stays "placed" for manual review.
        $order->forceFill(['payment_status' => 'paid', 'paid_bdt' => $order->total_bdt])->save();
        app(OrderWorkflow::class)->afterPayment($order);
        $this->assertSame('placed', $order->refresh()->status);

        // Hidden from JSON.
        $this->assertArrayNotHasKey('fraud_score', Order::query()->find($order->id)->toArray());
    }

    public function test_low_risk_order_keeps_advance_and_auto_verifies(): void
    {
        $this->fakeSteadfast(10, 0);
        $order = $this->makeOrder(['status' => 'placed', 'payment_status' => 'pending', 'paid_bdt' => 0, 'payment_method' => 'advance50', 'payment_via' => 'bkash']);
        $this->assertFalse((bool) $order->fraud_review);
        $this->assertEquals(ceil($order->total_bdt / 2), app(PaymentService::class)->amountNow($order));
        $order->forceFill(['payment_status' => 'partial', 'paid_bdt' => 1300])->save();
        app(OrderWorkflow::class)->afterPayment($order);
        $this->assertSame('verified', $order->refresh()->status);
    }

    public function test_internal_signals_velocity_and_disposable_email(): void
    {
        Http::fake();
        $user = $this->customer('+8801733000000', ['email' => 'x@mailinator.com']);
        $this->makeOrder(['status' => 'cancelled'], [], $user);
        $this->makeOrder(['status' => 'cancelled'], [], $user);
        $order = $this->makeOrder(['status' => 'placed'], [], $user);
        $r = app(FraudService::class)->score($order->refresh());
        $text = implode(' | ', $r['reasons']);
        $this->assertStringContainsString('cancelled orders before', $text);
        $this->assertStringContainsString('disposable email', $text);
        $this->assertStringContainsString('orders in the last hour', $text);
    }

    public function test_fraud_check_page_and_blocklist_resource_render(): void
    {
        $this->fakeSteadfast(3, 1);
        $this->actingAs($this->staff());
        $this->get(FraudCheck::getUrl())->assertOk();
        Livewire::test(FraudCheck::class)->fillForm(['phone' => '01744000000'])->call('check')
            ->assertSet('result.courier_total', 4);
        $this->get(BlockedEntryResource::getUrl())->assertOk();
    }
}
