<?php

namespace Tests\Feature\Commerce;

use App\Services\Commerce\Payments\MockGateway;
use App\Services\Commerce\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    public function test_mock_page_and_success_callback_is_idempotent(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)], ['returnUrl' => 'http://localhost:3000/checkout/success?from=app']);
        $pay = $o->payments()->first();

        // Signed mock page renders; unsigned is rejected.
        $redirect = app(PaymentService::class)->gateway('bkash')->initiate($pay, $o, 'x')['redirectUrl'];
        $this->get($redirect)->assertOk()->assertSee('TEST MODE');
        $this->get('/api/v1/payments/mock/'.$pay->id)->assertForbidden();

        $body = ['status' => 'success', 'signature' => MockGateway::signature($pay->id, 'success')];
        $this->post('/api/v1/webhooks/payments/mock?payment='.$pay->id.'&return=1', $body)
            ->assertRedirect('http://localhost:3000/checkout/success?from=app&order='.$o->id.'&status=paid');

        $o->refresh();
        $this->assertSame('paid', $o->payment_status);
        $this->assertEquals($o->total_bdt, $o->paid_bdt);
        $this->assertSame('verified', $o->status);

        // Replay: no double credit.
        $this->postJson('/api/v1/webhooks/payments/mock?payment='.$pay->id, $body)->assertOk()->assertJsonPath('status', 'paid');
        $this->assertEquals($o->total_bdt, $o->refresh()->paid_bdt);
        $this->assertSame(2, $o->events()->count());
    }

    public function test_app_deep_link_return_url(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)], ['returnUrl' => 'skydropretail://checkout/success']);
        $pay = $o->payments()->first();
        $this->post('/api/v1/webhooks/payments/mock?payment='.$pay->id.'&return=1', ['status' => 'cancelled', 'signature' => MockGateway::signature($pay->id, 'cancelled')])
            ->assertRedirect('skydropretail://checkout/success?order='.$o->id.'&status=cancelled');
    }

    public function test_forged_callback_is_rejected(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $pay = $o->payments()->first();
        $this->postJson('/api/v1/webhooks/payments/mock?payment='.$pay->id, ['status' => 'success', 'signature' => 'nope'])->assertStatus(400);
        $this->assertSame('pending', $o->refresh()->payment_status);
    }

    public function test_failed_payment_marks_failed(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $pay = $o->payments()->first();
        $this->postJson('/api/v1/webhooks/payments/mock?payment='.$pay->id, ['status' => 'failed', 'signature' => MockGateway::signature($pay->id, 'failed')])->assertOk();
        $this->assertSame('failed', $o->refresh()->payment_status);
        $this->assertSame('placed', $o->status);

        // Retry creates a new payment.
        $this->postJson("/api/v1/orders/{$o->id}/pay")->assertOk()->assertJsonPath('payment.status', 'redirect');
        $this->assertSame(2, $o->payments()->count());
    }

    public function test_advance50_is_partial_with_cod_balance(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)], ['payment' => 'advance50', 'paymentVia' => 'nagad']);
        $pay = $o->payments()->first();
        $this->assertEquals(ceil($o->total_bdt / 2), $pay->amount_bdt);
        $this->assertSame('advance', $pay->kind);

        $this->postJson('/api/v1/webhooks/payments/mock?payment='.$pay->id, ['status' => 'success', 'signature' => MockGateway::signature($pay->id, 'success')])->assertOk();
        $o->refresh();
        $this->assertSame('partial', $o->payment_status);
        $this->assertEquals($o->total_bdt - $pay->amount_bdt, $o->balanceBdt());
        $this->getJson("/api/v1/orders/{$o->id}")->assertJsonPath('order.paymentStatus', 'partial')
            ->assertJsonPath('order.dueOnDelivery', (int) round($o->balanceBdt()));
    }
}
