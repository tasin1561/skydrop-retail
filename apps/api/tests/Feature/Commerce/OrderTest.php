<?php

namespace Tests\Feature\Commerce;

use App\Models\Coupon;
use App\Models\Order;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Payments\MockGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    public function test_creates_order_with_snapshots_and_redirect(): void
    {
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        $r = $this->postJson('/api/v1/orders', [
            'items' => [['productId' => 'A1', 'qty' => 2, 'unitBdt' => $this->bdt($p)]],
            'address' => $this->addressPayload(), 'payment' => 'bkash', 'coupon' => 'SKYDROP10',
            'returnUrl' => 'http://localhost:3000/checkout/success?order=x',
        ])->assertCreated();

        $id = $r->json('order.id');
        $this->assertMatchesRegularExpression('/^SR-[A-Z0-9]{6}$/', $id);
        $this->assertMatchesRegularExpression('/^SD\d{9}$/', $r->json('order.trackingCode'));
        $r->assertJsonPath('order.status', 'placed')->assertJsonPath('order.paymentStatus', 'pending')
            ->assertJsonPath('order.payment', 'bKash')->assertJsonPath('order.items.0.unitBdt', $this->bdt($p))
            ->assertJsonPath('order.address.district', 'Cumilla')->assertJsonPath('order.timeline.0.status', 'placed')
            ->assertJsonPath('payment.status', 'redirect');
        $this->assertStringContainsString('/api/v1/payments/mock/', $r->json('payment.redirectUrl'));

        $this->assertSame(1, $u->addresses()->count()); // inline address saved
        $this->assertDatabaseHas('order_items', ['order_id' => $id, 'unit_inr' => 1000, 'qty' => 2]);
        $this->assertDatabaseHas('order_verifications', ['order_id' => $id, 'product_id' => 'A1', 'ok' => true]);
        $this->assertDatabaseHas('order_events', ['order_id' => $id, 'status' => 'placed']);
        $this->assertDatabaseHas('payments', ['order_id' => $id, 'gateway' => 'mock', 'status' => 'pending']);
        $this->assertSame(1, Coupon::query()->where('code', 'SKYDROP10')->value('used'));

        $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('items.0.id', $id);
        $this->getJson("/api/v1/orders/{$id}")->assertOk()->assertJsonPath('order.id', $id);
        $this->get("/api/v1/orders/{$id}/invoice")->assertOk()->assertSee($id)->assertSee('Skydrop');
    }

    public function test_price_change_beyond_three_percent_returns_409(): void
    {
        $u = $this->shopper();
        $this->address($u);
        $p = $this->product('A1', 1000);
        $stale = (int) round($this->bdt($p) * 0.9);

        $this->postJson('/api/v1/orders', [
            'items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $stale]], 'addressId' => $u->addresses()->first()->id, 'payment' => 'nagad',
        ])->assertStatus(409)->assertJsonPath('code', 'price_changed')->assertJsonPath('prices.0.freshBdt', $this->bdt($p))
            ->assertJsonPath('prices.0.oldBdt', $stale);
        $this->assertSame(0, Order::query()->count());

        // Within 3%: accepted at the fresh (server) price.
        $close = $this->bdt($p) - 20;
        $r = $this->postJson('/api/v1/orders', [
            'items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $close]], 'addressId' => $u->addresses()->first()->id, 'payment' => 'nagad',
        ])->assertCreated();
        $this->assertSame($this->bdt($p), $r->json('order.items.0.unitBdt'));
    }

    public function test_out_of_stock_is_rejected(): void
    {
        $u = $this->shopper();
        $this->address($u);
        $p = $this->product('A1', 1000, extra: ['in_stock' => false]);
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]],
            'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])->assertStatus(422)->assertJsonPath('code', 'unavailable');
    }

    public function test_return_url_must_point_to_storefront(): void
    {
        $u = $this->shopper();
        $this->address($u);
        $p = $this->product('A1', 1000);
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]],
            'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash', 'returnUrl' => 'https://evil.example/x'])
            ->assertStatus(422)->assertJsonValidationErrors('returnUrl');
    }

    public function test_cancel_allowed_only_before_purchase(): void
    {
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        $o1 = $this->placeOrder($u, [$p]);
        $this->postJson("/api/v1/orders/{$o1->id}/cancel")->assertOk()->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.timeline.1.status', 'cancelled');

        $o2 = $this->placeOrder($u, [$p]);
        $wf = app(OrderWorkflow::class);
        $wf->transition($o2, 'verified', 'admin');
        $wf->transition($o2->refresh(), 'purchased', 'admin');
        $this->postJson("/api/v1/orders/{$o2->id}/cancel")->assertStatus(409)->assertJsonPath('code', 'not_cancellable');
    }

    public function test_cancel_refunds_paid_order(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $pay = $o->payments()->first();
        $this->post('/api/v1/webhooks/payments/mock?payment='.$pay->id, ['status' => 'success',
            'signature' => MockGateway::signature($pay->id, 'success')])->assertOk();
        $this->assertSame('paid', $o->refresh()->payment_status);

        $this->postJson("/api/v1/orders/{$o->id}/cancel")->assertOk()->assertJsonPath('order.paymentStatus', 'refunded');
        $this->assertDatabaseHas('payments', ['id' => $pay->id, 'status' => 'refunded']);
    }

    public function test_orders_are_private(): void
    {
        $owner = $this->shopper(['phone' => '+8801911111111']);
        $o = $this->placeOrder($owner, [$this->product('A1', 1000)]);
        $this->shopper();
        $this->getJson("/api/v1/orders/{$o->id}")->assertNotFound();
        $this->postJson("/api/v1/orders/{$o->id}/cancel")->assertNotFound();
    }

    public function test_workflow_rejects_backward_moves(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $wf = app(OrderWorkflow::class);
        $wf->transition($o, 'india_hub', 'admin');
        $this->expectException(CommerceException::class);
        $wf->transition($o->refresh(), 'verified', 'admin');
    }
}
