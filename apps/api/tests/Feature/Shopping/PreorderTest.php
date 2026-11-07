<?php

namespace Tests\Feature\Shopping;

use App\Jobs\Shopping\ProcessPreorders;
use App\Models\Order;
use App\Models\PreorderRule;
use App\Models\ProductWatch;
use App\Services\Commerce\Sms\LogSms;
use App\Services\Shopping\PreorderService;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreorderTest extends TestCase
{
    use RefreshDatabase, ShoppingHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopping();
    }

    public function test_deposit_then_balance_when_back_in_stock(): void
    {
        $p = $this->product('PO1', 2000, 'amazon', 'electronics', ['in_stock' => false]);
        $u = $this->shopper();
        $this->address($u);

        // No rule → not available; the waitlist count still shows.
        ProductWatch::query()->create(['user_id' => $u->id, 'product_id' => 'PO1', 'type' => 'back_in_stock', 'active' => true]);
        $this->getJson('/api/v1/products/PO1/preorder')->assertOk()->assertJsonPath('available', false)->assertJsonPath('waitlist', 1)
            ->assertJsonPath('watching', true);
        $this->postJson('/api/v1/preorders', ['productId' => 'PO1', 'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])
            ->assertStatus(422)->assertJsonPath('code', 'not_preorderable');

        PreorderRule::query()->create(['scope' => 'category', 'target' => 'electronics', 'deposit_pct' => 30, 'eta_days' => 14]);
        $price = $this->bdt($p);
        $this->getJson('/api/v1/products/PO1/preorder')->assertJsonPath('available', true)->assertJsonPath('depositPct', 30)
            ->assertJsonPath('depositBdt', (int) ceil($price * 0.3))->assertJsonPath('etaDays', 14);

        $r = $this->postJson('/api/v1/preorders', ['productId' => 'PO1', 'qty' => 1, 'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])
            ->assertCreated()->assertJsonPath('order.isPreorder', true)->assertJsonPath('order.preorder.state', 'awaiting_stock');
        $o = Order::query()->findOrFail($r->json('order.id'));
        $deposit = (int) ceil($o->total_bdt * 0.3);
        $this->assertEquals($deposit, $o->deposit_bdt);
        $r->assertJsonPath('payment.amount', $deposit);

        // Deposit paid → partial, still "placed" (not bought until the balance is in).
        $o = $this->pay($o);
        $this->assertSame('partial', $o->payment_status);
        $this->assertSame('placed', $o->status);

        // Still out of stock → nothing happens.
        (new ProcessPreorders)->handle(app(PreorderService::class));
        $this->assertSame('awaiting_stock', $o->refresh()->preorder_state);

        // Back in stock → balance due + SMS with a pay link (once).
        $p->update(['in_stock' => true]);
        LogSms::$sent = [];
        (new ProcessPreorders)->handle(app(PreorderService::class));
        $this->assertSame('balance_due', $o->refresh()->preorder_state);
        $this->assertNotNull($o->balance_reminded_at);
        $this->assertStringContainsString('/orders/'.$o->id.'?pay=1', json_encode(LogSms::$sent, JSON_UNESCAPED_SLASHES));

        // Balance payment → verified, then ready.
        $pay = $this->postJson("/api/v1/orders/{$o->id}/pay")->assertOk();
        $this->assertEquals($o->total_bdt - $deposit, $pay->json('payment.amount'));
        $o = $this->pay($o);
        $this->assertSame('paid', $o->payment_status);
        $this->assertSame('verified', $o->status);
        (new ProcessPreorders)->handle(app(PreorderService::class));
        $this->assertSame('ready', $o->refresh()->preorder_state);
    }

    public function test_in_stock_items_and_switch(): void
    {
        $this->product('PO2', 1000);
        PreorderRule::query()->create(['scope' => 'product', 'target' => 'PO2', 'deposit_pct' => 30, 'eta_days' => 10]);
        $u = $this->shopper();
        $this->address($u);
        $this->getJson('/api/v1/products/PO2/preorder')->assertJsonPath('available', false)->assertJsonPath('inStock', true);

        Features::set('preorders', false);
        $this->postJson('/api/v1/preorders', ['productId' => 'PO2', 'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])->assertNotFound();
    }
}
