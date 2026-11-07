<?php

namespace Tests\Feature\Security;

use App\Models\Coupon;
use App\Models\Payment;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Payments\NagadGateway;
use App\Services\Commerce\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

/** Security audit 2026-11: fail-open defaults (OTP echo, mock payments), payment callback binding, coupon races. */
class MoneyAndAuthTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    private function asProduction(callable $fn): void
    {
        $env = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $fn();
        } finally {
            $this->app['env'] = $env;
        }
    }

    public function test_log_otp_driver_never_echoes_codes_in_production(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01711000001'])->assertOk()->assertJsonStructure(['demoCode']);

        $this->asProduction(function () {
            $this->postJson('/api/v1/auth/otp/request', ['phone' => '01711000002'])
                ->assertStatus(503)->assertJsonPath('code', 'otp_unavailable')->assertJsonMissingPath('demoCode');
        });
    }

    public function test_mock_payments_are_refused_in_production(): void
    {
        $this->asProduction(function () {
            $this->assertFalse(PaymentService::mockAllowed());
            try {
                app(PaymentService::class)->gateway('bkash');
                $this->fail('mock gateway must not be used in production');
            } catch (CommerceException $e) {
                $this->assertSame(503, $e->status);
            }
            config(['retail.payments.allow_mock' => true]); // explicit opt-in for a production-like staging box
            $this->assertTrue(PaymentService::mockAllowed());
        });
    }

    public function test_nagad_callback_cannot_reuse_another_payments_reference(): void
    {
        $u = $this->shopper();
        $order = $this->placeOrder($u, [$this->product()]);
        config(['retail.payments.mode' => 'sandbox']);
        $amount = (float) $order->total_bdt;
        $verify = fn (string $orderId) => Http::response(['status' => 'Success', 'amount' => number_format($amount, 2, '.', ''),
            'orderId' => $orderId, 'issuerPaymentRefNo' => 'TRX1']);

        // Initialize failed (no reference stored): a reference from someone else's paid Nagad payment is rejected.
        $orphan = Payment::query()->create(['order_id' => $order->id, 'gateway' => 'nagad', 'amount_bdt' => $amount, 'status' => 'failed', 'kind' => 'full']);
        $nagadOrderId = 'SRXXXXXXP999';
        Http::fake(['*' => function () use (&$nagadOrderId, $verify) {
            return $verify($nagadOrderId);
        }]);
        $this->getJson('/api/v1/webhooks/payments/nagad?payment='.$orphan->id.'&payment_ref_id=OTHER-REF&status=Success')->assertStatus(400);
        $this->assertNotSame('paid', $orphan->fresh()->status);

        // Matching reference but Nagad says the money was for another order id.
        $p = Payment::query()->create(['order_id' => $order->id, 'gateway' => 'nagad', 'amount_bdt' => $amount, 'status' => 'pending', 'kind' => 'full', 'gateway_ref' => 'REF-1']);
        $this->getJson('/api/v1/webhooks/payments/nagad?payment='.$p->id.'&payment_ref_id=REF-1&status=Success');
        $this->assertNotSame('paid', $p->fresh()->status);

        // Positive control: the right reference for the right order is accepted.
        $nagadOrderId = NagadGateway::orderRef($p);
        $p->forceFill(['status' => 'pending'])->save();
        $this->getJson('/api/v1/webhooks/payments/nagad?payment='.$p->id.'&payment_ref_id=REF-1&status=Success&return=0', ['Accept' => 'application/json']);
        $this->assertSame('paid', $p->fresh()->status);
    }

    public function test_coupon_claim_never_exceeds_max_uses(): void
    {
        $c = Coupon::query()->create(['code' => 'ONEUSE', 'type' => 'fixed', 'value' => 100, 'active' => true, 'max_uses' => 1, 'used' => 0]);
        $this->assertTrue(Coupon::claim($c->id));
        $this->assertFalse(Coupon::claim($c->id));
        $this->assertSame(1, $c->fresh()->used);

        $c->forceFill(['max_uses' => null, 'expires_at' => now()->subDay()])->save();
        $this->assertFalse(Coupon::claim($c->id), 'expired');
    }
}
