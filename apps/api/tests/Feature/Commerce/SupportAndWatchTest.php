<?php

namespace Tests\Feature\Commerce;

use App\Models\ProductWatch;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Sms\LogSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAndWatchTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    public function test_returns_only_within_three_days_of_delivery(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $req = ['type' => 'damaged', 'message' => 'The box was crushed.'];

        $this->postJson("/api/v1/orders/{$o->id}/support", $req)->assertStatus(422)->assertJsonPath('code', 'not_delivered');
        $this->postJson("/api/v1/orders/{$o->id}/support", ['type' => 'other', 'message' => 'When will it ship?'])->assertCreated();

        app(OrderWorkflow::class)->transition($o, 'delivered', 'admin');
        $this->postJson("/api/v1/orders/{$o->id}/support", $req)->assertCreated()->assertJsonPath('request.status', 'open');

        $o->refresh()->forceFill(['delivered_at' => now()->subDays(4)])->save();
        $this->postJson("/api/v1/orders/{$o->id}/support", ['type' => 'return', 'message' => 'Changed my mind'])
            ->assertStatus(422)->assertJsonPath('code', 'window_closed');

        $this->getJson('/api/v1/support')->assertOk()->assertJsonCount(2, 'items');
    }

    public function test_price_drop_and_back_in_stock_watches_notify(): void
    {
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        $q = $this->product('B1', 1000, extra: ['in_stock' => false]);
        $this->postJson('/api/v1/watches', ['productId' => 'A1', 'type' => 'price_drop'])->assertCreated();
        $this->postJson('/api/v1/watches', ['productId' => 'B1', 'type' => 'back_in_stock'])->assertCreated();
        LogSms::$sent = [];

        $p->update(['price_inr' => 800]); // product update triggers the job (sync queue)
        $q->update(['in_stock' => true]);

        $msgs = array_column(LogSms::$sent, 'message');
        $this->assertTrue(collect($msgs)->contains(fn ($m) => str_contains($m, 'price drop')));
        $this->assertTrue(collect($msgs)->contains(fn ($m) => str_contains($m, 'back in stock')));
        $this->assertFalse((bool) ProductWatch::query()->where('type', 'back_in_stock')->value('active'));
    }

    public function test_wishlist(): void
    {
        $this->shopper();
        $this->product('A1', 1000);
        $this->postJson('/api/v1/wishlist', ['productId' => 'A1'])->assertCreated();
        $this->postJson('/api/v1/wishlist', ['productId' => 'A1'])->assertCreated(); // idempotent
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', 'A1');
        $this->deleteJson('/api/v1/wishlist/A1')->assertOk();
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonCount(0, 'items');
    }
}
