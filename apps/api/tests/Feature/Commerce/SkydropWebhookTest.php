<?php

namespace Tests\Feature\Commerce;

use App\Models\Order;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Skydrop\SkydropClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SkydropWebhookTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    private function hook(array $payload, ?string $sig = null): TestResponse
    {
        $raw = json_encode($payload);

        return $this->call('POST', '/api/v1/webhooks/skydrop', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SKYDROP_SIGNATURE' => $sig ?? hash_hmac('sha256', $raw, 'test-secret'),
        ], $raw);
    }

    private function purchasedOrder(): Order
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $wf = app(OrderWorkflow::class);
        $wf->transition($o, 'verified', 'admin');

        return $wf->transition($o->refresh(), 'purchased', 'admin');
    }

    public function test_purchase_creates_mock_consignment(): void
    {
        $o = $this->purchasedOrder();
        $this->assertStringStartsWith('MOCK-CN-', (string) $o->refresh()->skydrop_consignment_id);
    }

    public function test_rejects_bad_signature(): void
    {
        $o = $this->purchasedOrder();
        $this->hook(['event' => 'in_transit', 'tracking_code' => $o->tracking_code], 'deadbeef')->assertStatus(401);
        $this->hook(['event' => 'in_transit', 'tracking_code' => $o->tracking_code], '')->assertStatus(401);
        $this->assertSame('purchased', $o->refresh()->status);
    }

    public function test_maps_events_to_statuses_and_is_idempotent(): void
    {
        $o = $this->purchasedOrder();
        $this->hook(['event' => 'consignment.received_at_origin_hub', 'tracking_code' => $o->tracking_code])->assertOk()->assertJsonPath('status', 'india_hub');
        $this->hook(['event' => 'border_crossing', 'tracking_code' => $o->tracking_code])->assertOk()->assertJsonPath('status', 'in_transit');
        $this->hook(['event' => 'border_crossing', 'tracking_code' => $o->tracking_code])->assertOk()->assertJsonPath('duplicate', true);
        $this->hook(['event' => 'unknown_thing', 'tracking_code' => $o->tracking_code])->assertOk()->assertJsonPath('ignored', 'unknown_thing');
        // sha256= prefix is accepted
        $raw = json_encode(['event' => 'dhaka_intake', 'tracking_code' => $o->tracking_code]);
        $this->hook(json_decode($raw, true), 'sha256='.hash_hmac('sha256', $raw, 'test-secret'))->assertOk()->assertJsonPath('status', 'dhaka_hub');
        $this->hook(['event' => 'delivered', 'data' => ['tracking_code' => $o->tracking_code]])->assertOk()->assertJsonPath('status', 'delivered');

        $o->refresh();
        $this->assertSame('delivered', $o->status);
        $this->assertNotNull($o->delivered_at);
        $this->assertSame(['placed', 'verified', 'purchased', 'india_hub', 'in_transit', 'dhaka_hub', 'delivered'], $o->events()->pluck('status')->all());
        $this->assertSame('skydrop', $o->events()->where('status', 'in_transit')->value('source'));
    }

    public function test_status_map(): void
    {
        $this->assertSame('out_for_delivery', SkydropClient::mapEvent('Out For Delivery'));
        $this->assertSame('india_hub', SkydropClient::mapEvent('picked_up'));
        $this->assertNull(SkydropClient::mapEvent('consignment.created'));
    }

    public function test_simulate_tracking_command(): void
    {
        $o = $this->purchasedOrder();
        $this->artisan('retail:simulate-tracking', ['order' => $o->id, '--all' => true])->assertSuccessful();
        $this->assertSame('delivered', $o->refresh()->status);
    }
}
