<?php

namespace Tests\Feature\Fulfilment;

use App\Jobs\Commerce\CreateSkydropConsignment;
use App\Models\Fulfilment\HubBox;
use App\Models\Fulfilment\HubTask;
use App\Models\Fulfilment\ReturnRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Skydrop\SkydropClient;
use App\Services\Fulfilment\FulfilmentSettings;
use App\Services\Fulfilment\HubService;
use App\Services\Fulfilment\PriceAlertService;
use App\Services\Fulfilment\ReturnService;
use App\Services\Fulfilment\UnboxingService;
use App\Support\Code128;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class FulfilmentTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
        Bus::fake([CreateSkydropConsignment::class]);
        Features::flush();
    }

    private function wf(): OrderWorkflow
    {
        return app(OrderWorkflow::class);
    }

    /** A paid order moved to `purchased` with marketplace refs on every item. */
    private function purchasedOrder(User $u, array $products, array $extra = []): Order
    {
        $order = $this->placeOrder($u, $products, $extra);
        $order->forceFill(['payment_status' => 'paid', 'paid_bdt' => $order->total_bdt])->save();
        $this->wf()->transition($order, 'verified', 'admin');
        foreach ($order->items as $i) {
            $i->forceFill(['purchase_ref' => '403-'.$i->id])->save();
        }

        return $this->wf()->transition($order->refresh(), 'purchased', 'admin');
    }

    public function test_hub_receive_moves_order_to_india_hub_when_all_items_arrive(): void
    {
        $u = $this->shopper();
        $a = $this->product('B0HUB00001', 1000);
        $b = $this->product('FKHUB00002', 500, 'flipkart');
        $order = $this->purchasedOrder($u, [$a, $b]);
        $hub = app(HubService::class);

        $found = $hub->find('403-'.$order->items[0]->id);
        $this->assertCount(1, $found);
        $this->assertCount(2, $hub->find($order->id));

        $hub->receive($found->first(), ['weight_kg' => 0.6, 'length_cm' => 20, 'width_cm' => 10, 'height_cm' => 5, 'condition' => 'ok']);
        $this->assertSame('purchased', $order->refresh()->status);
        $this->assertSame(['amazon' => ['received' => 1, 'total' => 1], 'flipkart' => ['received' => 0, 'total' => 1]], $hub->progress($order));

        $hub->receive($order->items[1], ['weight_kg' => 0.4, 'condition' => 'ok']);
        $order->refresh();
        $this->assertSame('india_hub', $order->status);
        $this->assertEqualsWithDelta(1.0, $order->actual_weight_kg, 0.001);
        $this->assertDatabaseCount('hub_receipts', 2);

        $box = $hub->consolidate($order);
        $this->assertInstanceOf(HubBox::class, $box);
        $this->assertSame(2, $box->item_count);
        $this->assertSame(2, $box->receipts()->count());
    }

    public function test_discrepancy_alerts_customer_and_opens_return_to_seller_task(): void
    {
        $u = $this->shopper();
        $order = $this->purchasedOrder($u, [$this->product('B0HUB00003', 800)]);
        app(HubService::class)->receive($order->items[0], ['weight_kg' => 0.5, 'condition' => 'damaged', 'notes' => 'Crushed box']);

        $this->assertSame(1, HubTask::query()->where('order_id', $order->id)->where('type', 'return_to_seller')->count());
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id]);
    }

    public function test_weight_variance_flags_order(): void
    {
        FulfilmentSettings::put('hub.weight_variance_pct', 10);
        $u = $this->shopper();
        $order = $this->purchasedOrder($u, [$this->product('B0HUB00004', 800)]);
        $estimate = $order->items[0]->weight_kg;
        app(HubService::class)->receive($order->items[0], ['weight_kg' => $estimate * 2, 'condition' => 'ok']);
        $flag = $order->refresh()->weight_flag;
        $this->assertNotNull($flag);
        $this->assertGreaterThan(10, $flag['variancePct']);
        $this->assertNull($flag['resolution']);
    }

    public function test_code128_patterns_are_valid(): void
    {
        foreach (Code128::PATTERNS as $i => $p) {
            $this->assertSame($i === 106 ? 13 : 11, array_sum(str_split($p)), "pattern {$i}");
        }
        $this->assertCount(107, array_unique(Code128::PATTERNS));
        $values = Code128::values('SR-ABC123');
        $this->assertSame(104, $values[0]);
        $this->assertSame(106, end($values));
        $this->assertStringContainsString('<svg', Code128::svg('SR-ABC123'));
    }

    public function test_unboxing_quote_order_photos_approval_and_auto_approve(): void
    {
        $u = $this->shopper();
        $p = $this->product('B0UNBOX001', 1200);
        $this->address($u);

        $q = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => $p->id, 'qty' => 1]], 'unboxing' => true])->assertOk();
        $this->assertSame(150, $q->json('unboxingFee'));
        $without = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => $p->id, 'qty' => 1]]])->json('total');
        $this->assertSame($without + 150, $q->json('total'));

        $order = $this->purchasedOrder($u, [$p], ['unboxing' => true]);
        $this->assertTrue($order->unboxing);
        $this->assertEquals(150, $order->unboxing_fee_bdt);

        // Photos are required for unboxing orders.
        try {
            app(HubService::class)->receive($order->items[0], ['weight_kg' => 0.5]);
            $this->fail('expected photos error');
        } catch (CommerceException $e) {
            $this->assertStringContainsString('photos', $e->getMessage());
        }
        app(HubService::class)->receive($order->items[0], ['weight_kg' => 0.5, 'photos' => ['hub/'.$order->id.'/a.jpg']]);
        $order->refresh();
        $this->assertSame('india_hub', $order->status);
        $this->assertSame(UnboxingService::AWAITING, $order->unboxing_status);
        $this->assertTrue(HubService::holdsShipping($order));

        $this->getJson("/api/v1/orders/{$order->id}/unboxing")->assertOk()
            ->assertJsonPath('unboxing.status', UnboxingService::AWAITING)->assertJsonCount(1, 'unboxing.items.0.photos');
        $this->postJson("/api/v1/orders/{$order->id}/unboxing/approve")->assertOk()->assertJsonPath('unboxing.status', UnboxingService::APPROVED);
        $this->assertFalse(HubService::holdsShipping($order->refresh()));

        // Auto-approve after 48 h without an answer.
        $o2 = $this->purchasedOrder($u, [$this->product('B0UNBOX002', 900)], ['unboxing' => true]);
        app(HubService::class)->receive($o2->items[0], ['weight_kg' => 0.5, 'photos' => ['hub/x.jpg']]);
        $this->assertSame(0, app(UnboxingService::class)->autoApproveDue());
        $this->travel(49)->hours();
        $this->assertSame(1, app(UnboxingService::class)->autoApproveDue());
        $this->assertSame(UnboxingService::AUTO, $o2->refresh()->unboxing_status);
    }

    public function test_unboxing_problem_opens_india_case(): void
    {
        $u = $this->shopper();
        $order = $this->purchasedOrder($u, [$this->product('B0UNBOX003', 900)], ['unboxing' => true]);
        app(HubService::class)->receive($order->items[0], ['weight_kg' => 0.5, 'photos' => ['hub/y.jpg']]);
        $this->postJson("/api/v1/orders/{$order->id}/unboxing/problem", ['reason' => 'wrong_item', 'note' => 'Wrong colour'])
            ->assertOk()->assertJsonPath('return.stage', 'india')->assertJsonPath('unboxing.status', UnboxingService::PROBLEM);
    }

    public function test_return_lifecycle_refund_and_replacement(): void
    {
        Storage::fake('local');
        $u = $this->shopper();
        $p = $this->product('B0RETURN01', 1500);
        $order = $this->purchasedOrder($u, [$p]);

        $this->getJson("/api/v1/orders/{$order->id}/returns")->assertOk()->assertJsonPath('eligibility.reason', 'not_delivered');
        foreach (['india_hub', 'in_transit', 'dhaka_hub', 'out_for_delivery', 'delivered'] as $s) {
            $this->wf()->transition($order->refresh(), $s, 'skydrop');
        }
        $this->getJson("/api/v1/orders/{$order->id}/returns")->assertOk()->assertJsonPath('eligibility.eligible', true)
            ->assertJsonMissing(['changed_mind']);

        $up = $this->post('/api/v1/uploads', ['file' => UploadedFile::fake()->image('broken.jpg', 400, 300)], ['Accept' => 'application/json'])
            ->assertCreated()->json('upload');
        $this->assertStringContainsString('signature=', $up['url']);
        $this->get($up['url'])->assertOk();
        $this->post('/api/v1/uploads', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);

        $this->postJson("/api/v1/orders/{$order->id}/returns", ['items' => [['orderItemId' => $order->items[0]->id]], 'reason' => 'changed_mind', 'resolution' => 'refund'])
            ->assertStatus(422);
        $this->postJson("/api/v1/orders/{$order->id}/returns", ['items' => [['orderItemId' => $order->items[0]->id]], 'reason' => 'damaged', 'resolution' => 'refund'])
            ->assertStatus(422); // photo required
        $id = $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'items' => [['orderItemId' => $order->items[0]->id, 'qty' => 1]], 'reason' => 'damaged', 'note' => 'Screen cracked',
            'photos' => [$up['id']], 'resolution' => 'refund',
        ])->assertCreated()->assertJsonPath('return.status', 'requested')->json('return.id');

        $svc = app(ReturnService::class);
        $r = ReturnRequest::query()->findOrFail($id);
        $r = $svc->approve($r);
        $this->assertFalse($svc->canMove($r, 'refunded')); // delivered-stage: must come back first
        $r = $svc->schedulePickup($r, now()->addDay());
        $r = $svc->markReceived($r);
        $r = $svc->refund($r);
        $this->assertSame('refunded', $r->status);
        $this->assertGreaterThan(0, $r->refund_bdt);
        $this->assertCount(5, $r->timeline);
        $this->getJson("/api/v1/orders/{$order->id}/returns")->assertJsonPath('items.0.status', 'refunded')
            ->assertJsonPath('eligibility.reason', 'already_requested');

        // Replacement: zero-charge order linked to the original.
        $o2 = $this->purchasedOrder($u, [$this->product('B0RETURN02', 700)]);
        foreach (['india_hub', 'in_transit', 'dhaka_hub', 'out_for_delivery', 'delivered'] as $s) {
            $this->wf()->transition($o2->refresh(), $s, 'skydrop');
        }
        $r2 = $svc->create($o2->refresh(), $u, ['items' => [['orderItemId' => $o2->items[0]->id]], 'reason' => 'size_issue', 'resolution' => 'replacement']);
        $r2 = $svc->markReceived($svc->approve($r2));
        $r2 = $svc->replace($r2);
        $new = Order::query()->findOrFail($r2->replacement_order_id);
        $this->assertSame($o2->id, $new->replacement_of);
        $this->assertEquals(0, $new->total_bdt);
        $this->assertSame('verified', $new->status);

        // Window closes after 7 days.
        $this->travel(8)->days();
        $this->getJson("/api/v1/orders/{$o2->id}/returns")->assertJsonPath('eligibility.reason', 'window_closed');
    }

    public function test_tracking_endpoint_with_rider_and_pod(): void
    {
        $u = $this->shopper();
        $order = $this->purchasedOrder($u, [$this->product('B0TRACK001', 900)]);
        $this->wf()->transition($order->refresh(), 'india_hub', 'skydrop');
        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertOk()
            ->assertJsonPath('tracking.currentLeg', 0)->assertJsonPath('tracking.points.3.short', 'Dhaka')->assertJsonCount(3, 'tracking.legs');

        foreach (['in_transit', 'dhaka_hub', 'out_for_delivery'] as $s) {
            $this->wf()->transition($order->refresh(), $s, 'skydrop');
        }
        $body = json_encode(['event' => 'rider_location', 'tracking_code' => $order->tracking_code, 'rider' => ['lat' => 23.79, 'lng' => 90.40, 'name' => 'Karim']]);
        $this->call('POST', '/api/v1/webhooks/skydrop', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SKYDROP_SIGNATURE' => SkydropClient::sign($body)], $body)->assertOk();
        $t = $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertOk()->json('tracking');
        $this->assertSame(2, $t['currentLeg']);
        $this->assertEqualsWithDelta(23.79, $t['rider']['lat'], 0.0001);
        $this->assertNotNull($t['eta']);

        $this->travel(11)->minutes();
        $this->assertNull($this->getJson("/api/v1/orders/{$order->id}/tracking")->json('tracking.rider'));

        $body = json_encode(['event' => 'delivered', 'tracking_code' => $order->tracking_code, 'pod' => ['photo_url' => 'https://cdn.skydrop.online/pod/1.jpg', 'received_by' => 'Rahim']]);
        $this->call('POST', '/api/v1/webhooks/skydrop', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SKYDROP_SIGNATURE' => SkydropClient::sign($body)], $body)->assertOk();
        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertJsonPath('tracking.pod.photoUrl', 'https://cdn.skydrop.online/pod/1.jpg')
            ->assertJsonPath('tracking.progress', 1);

        // Switchable from admin.
        Features::set('live_tracking_map', false);
        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertNotFound()->assertJsonPath('error', 'feature_disabled');
    }

    public function test_simulate_tracking_rider_pings(): void
    {
        $u = $this->shopper();
        $order = $this->purchasedOrder($u, [$this->product('B0TRACK002', 900)]);
        $this->artisan('retail:simulate-tracking', ['order' => $order->id, '--steps' => 4, '--rider' => 3])->assertSuccessful();
        $this->assertSame('out_for_delivery', $order->refresh()->status);
        $this->assertDatabaseCount('skydrop_events', 3);
        $this->assertNotNull($order->rider_location);
    }

    public function test_price_alert_flags_item_and_actions(): void
    {
        FulfilmentSettings::put('price_alerts.rise_pct', 5);
        User::query()->forceCreate(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'secret-pass', 'is_admin' => true]);
        $u = $this->shopper();
        $p = $this->product('B0PRICE001', 1000);
        $q = $this->product('B0PRICE002', 400);
        $order = $this->placeOrder($u, [$p, $q]);
        $order->forceFill(['payment_status' => 'paid', 'paid_bdt' => $order->total_bdt])->save();
        $svc = app(PriceAlertService::class);

        $this->assertSame(0, $svc->checkProduct($p->id)); // unchanged
        $p->forceFill(['price_inr' => 1100])->save(); // +10 %
        $this->assertSame(2, $svc->checkPending(false));
        $item = OrderItem::query()->where('order_id', $order->id)->where('product_id', $p->id)->first();
        $this->assertEquals(1000, $item->price_alert['oldInr']);
        $this->assertEquals(1100, $item->price_alert['newInr']);
        $this->assertLessThan($item->price_alert['marginBeforeBdt'], $item->price_alert['marginAfterBdt']);
        $this->assertTrue($order->refresh()->price_alert);
        $this->assertDatabaseHas('notifications', ['notifiable_type' => User::class]);

        // Ask the customer to pay the difference → balance due + pay-difference endpoint.
        $total = $order->total_bdt;
        $svc->askCustomer($item);
        $order->refresh();
        $this->assertFalse($order->price_alert);
        $this->assertSame('partial', $order->payment_status);
        $this->assertGreaterThan($total, $order->total_bdt);
        $this->postJson("/api/v1/orders/{$order->id}/pay-difference")->assertOk()->assertJsonPath('payment.amount', (int) round($order->balanceBdt()));

        // Out of stock → cancel the item with a refund.
        $q->forceFill(['in_stock' => false])->save();
        $this->assertSame(1, $svc->checkProduct($q->id));
        $qi = OrderItem::query()->where('order_id', $order->id)->where('product_id', $q->id)->first();
        $svc->cancelItem($qi);
        $this->assertNotNull($qi->refresh()->cancelled_at);
        $this->assertNotSame('cancelled', $order->refresh()->status); // the other item is still active
    }

    public function test_feature_switches_hide_customer_endpoints(): void
    {
        $u = $this->shopper();
        $order = $this->purchasedOrder($u, [$this->product('B0FEAT0001', 900)]);
        Features::set('returns', false);
        $this->getJson("/api/v1/orders/{$order->id}/returns")->assertNotFound();
        Features::set('unboxing', false);
        $this->assertSame(0, $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'B0FEAT0001', 'qty' => 1]], 'unboxing' => true])->json('unboxingFee'));
    }
}
