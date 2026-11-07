<?php

namespace Tests\Feature\Money;

use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Jobs\Commerce\CreateSkydropConsignment;
use App\Models\FlashSale;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Cms\FlashSaleService;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Fulfilment\FulfilmentSettings;
use App\Services\Fulfilment\ReturnService;
use App\Services\Money\MembershipService;
use App\Services\PricingService;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

/** Skydrop Plus benefits wired outside checkout: free unboxing, free returns, priority support, early flash sales. */
class MembershipBenefitsTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
        Bus::fake([CreateSkydropConsignment::class]);
        Features::set('membership', true);
        Features::set('wallet', true);
        Features::set('returns', true);
        Features::set('unboxing', true);
        Features::set('flash_sales', true);
    }

    private function member(User $u, array $benefits): void
    {
        $plan = MembershipPlan::query()->create(['name' => 'Plus', 'slug' => 'plus-'.uniqid(), 'price_bdt' => 299, 'period' => 'monthly',
            'active' => true, 'benefits' => $benefits]);
        app(MembershipService::class)->extendOrStart($u, $plan, 1, ['source' => 'test']);
    }

    private function deliveredOrder(User $u, string $productId): Order
    {
        $order = $this->placeOrder($u, [$this->product($productId, 1500)]);
        $order->forceFill(['payment_status' => 'paid', 'paid_bdt' => $order->total_bdt])->save();
        $wf = app(OrderWorkflow::class);
        foreach (['verified', 'purchased', 'india_hub', 'in_transit', 'dhaka_hub', 'out_for_delivery', 'delivered'] as $s) {
            $wf->transition($order->refresh(), $s, 'admin');
        }

        return $order->refresh();
    }

    public function test_free_unboxing_waives_the_fee_for_members(): void
    {
        $u = $this->shopper();
        $this->product('UB1', 1000);
        $body = ['items' => [['productId' => 'UB1', 'qty' => 1]], 'unboxing' => true];
        $this->postJson('/api/v1/checkout/quote', $body)->assertOk()->assertJsonPath('unboxing.waived', false)->assertJsonPath('unboxingFee', 150);

        $this->member($u, ['free_unboxing' => true]);
        $this->postJson('/api/v1/checkout/quote', $body)->assertOk()->assertJsonPath('unboxing.waived', true)->assertJsonPath('unboxingFee', 0);
    }

    public function test_free_returns_skip_the_return_shipping_fee(): void
    {
        FulfilmentSettings::put('returns.shipping_fee_bdt', 120);
        $svc = app(ReturnService::class);

        // Non-member, customer-choice reason: fee deducted from the refund.
        $u = $this->shopper();
        $o = $this->deliveredOrder($u, 'RT1');
        $this->getJson("/api/v1/orders/{$o->id}/returns")->assertOk()
            ->assertJsonPath('eligibility.returnFee.amountBdt', 120)->assertJsonPath('eligibility.returnFee.waived', false);
        $item = $o->items->first();
        $r = $svc->create($o, $u, ['items' => [['orderItemId' => $item->id]], 'reason' => 'size_issue', 'resolution' => 'refund']);
        $this->assertEquals(120, $r->return_fee_bdt);
        $this->assertEquals($item->unit_bdt - 120, $r->refund_bdt);
        $this->assertFalse($r->priority);

        // Our fault (damaged): always free.
        $this->assertSame(0.0, $svc->returnFee($u, 'damaged'));

        // Member with free returns: no fee.
        $m = $this->shopper(['phone' => '+8801712000001']);
        $this->member($m, ['free_returns' => true]);
        $o2 = $this->deliveredOrder($m, 'RT2');
        $this->getJson("/api/v1/orders/{$o2->id}/returns")->assertOk()
            ->assertJsonPath('eligibility.returnFee.amountBdt', 0)->assertJsonPath('eligibility.returnFee.waived', true);
        $r2 = $svc->create($o2, $m, ['items' => [['orderItemId' => $o2->items->first()->id]], 'reason' => 'size_issue', 'resolution' => 'refund']);
        $this->assertEquals(0, $r2->return_fee_bdt);
        $this->assertEquals($o2->items->first()->unit_bdt, $r2->refund_bdt);
        $this->assertSame(0, $r2->toApi()['returnFeeBdt']);
    }

    public function test_priority_support_flags_requests_and_admin_lists_sort_and_filter(): void
    {
        $u = $this->shopper();
        $plain = $this->deliveredOrder($u, 'PS1');
        $this->postJson("/api/v1/orders/{$plain->id}/support", ['type' => 'other', 'message' => 'Where is my invoice?'])
            ->assertCreated()->assertJsonPath('request.priority', false);

        $m = $this->shopper(['phone' => '+8801712000002']);
        $this->member($m, ['priority_support' => true]);
        $order = $this->deliveredOrder($m, 'PS2');
        $this->postJson("/api/v1/orders/{$order->id}/support", ['type' => 'other', 'message' => 'Please call me back.'])
            ->assertCreated()->assertJsonPath('request.priority', true);
        $ret = app(ReturnService::class)->create($order, $m, ['items' => [['orderItemId' => $order->items->first()->id]], 'reason' => 'size_issue', 'resolution' => 'refund']);
        $this->assertTrue($ret->priority);
        $oldRet = app(ReturnService::class)->create($plain, $u, ['items' => [['orderItemId' => $plain->items->first()->id]], 'reason' => 'size_issue', 'resolution' => 'refund']);
        $oldRet->forceFill(['created_at' => now()->addMinute()])->save(); // newer, but not priority

        $priority = SupportRequest::query()->where('priority', true)->firstOrFail();
        $normal = SupportRequest::query()->where('priority', false)->firstOrFail();
        $normal->forceFill(['created_at' => now()->addMinute()])->save();

        $admin = new User;
        $admin->forceFill(['name' => 'Boss', 'email' => 'boss@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();
        $this->actingAs($admin);

        Livewire::test(ListSupportRequests::class)->assertCanSeeTableRecords([$priority, $normal], inOrder: true)
            ->assertSee('Plus priority')
            ->filterTable('priority', true)->assertCanSeeTableRecords([$priority])->assertCanNotSeeTableRecords([$normal]);
        Livewire::test(ListReturnRequests::class)->assertCanSeeTableRecords([$ret, $oldRet], inOrder: true)
            ->filterTable('priority', false)->assertCanSeeTableRecords([$oldRet])->assertCanNotSeeTableRecords([$ret]);
    }

    public function test_early_flash_sale_access_for_members_only(): void
    {
        $p = $this->product('FS1', 1000);
        $regular = app(PricingService::class)->basePriceBdt($p);
        $sale = FlashSale::query()->create(['title' => 'Early bird', 'store' => 'all', 'starts_at' => now()->addMinutes(20),
            'ends_at' => now()->addHours(2), 'active' => true]);
        $sale->items()->create(['product_id' => 'FS1', 'off_bdt' => 50]);

        // Non-member: still upcoming, regular price.
        $u = $this->shopper();
        $this->assertNull(app(FlashSaleService::class)->forProduct($p));
        $this->getJson('/api/v1/cms/home')->assertOk()->assertJsonPath('flashSales.0.status', 'upcoming')
            ->assertJsonPath('flashSales.0.earlyAccess', false)->assertHeader('Cache-Control', 'max-age=60, public');
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'FS1', 'qty' => 1]]])->assertOk()->assertJsonPath('flashDiscount', 0);

        // Member with 30 minutes early access: live for them, flash price in product JSON and the quote.
        $m = $this->shopper(['phone' => '+8801712000003']);
        $this->member($m, ['early_flash_sale_access_minutes' => 30]);
        $this->getJson('/api/v1/cms/home')->assertOk()->assertJsonPath('flashSales.0.status', 'live')
            ->assertJsonPath('flashSales.0.earlyAccess', true)->assertJsonPath('flashSales.0.items.0.product.priceBdt', $regular - 50);
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'FS1', 'qty' => 1]]])->assertOk()->assertJsonPath('flashDiscount', 50);

        // Member with only 10 minutes: not yet.
        $short = $this->shopper(['phone' => '+8801712000004']);
        $this->member($short, ['early_flash_sale_access_minutes' => 10]);
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'FS1', 'qty' => 1]]])->assertOk()->assertJsonPath('flashDiscount', 0);

        // Back to the non-member: unaffected.
        Sanctum::actingAs($u);
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'FS1', 'qty' => 1]]])->assertOk()->assertJsonPath('flashDiscount', 0);
    }
}
