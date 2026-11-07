<?php

namespace Tests\Feature\Shopping;

use App\Jobs\Shopping\CloseDueGroups;
use App\Models\GroupBuy;
use App\Models\Order;
use App\Models\User;
use App\Services\PricingService;
use App\Services\Shopping\GroupBuyService;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupBuyTest extends TestCase
{
    use RefreshDatabase, ShoppingHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopping();
    }

    public function test_group_lifecycle_with_shipping_split(): void
    {
        $a = $this->product('GA1', 1000); // 0.4 kg each (electronics default)
        $b = $this->product('GB1', 1500);
        $this->product('GF1', 500, 'flipkart', 'fashion');

        $owner = $this->shopper();
        $this->address($owner);
        $g = $this->postJson('/api/v1/groups', ['market' => 'amazon', 'hours' => 24, 'items' => [['productId' => 'GA1', 'qty' => 1]]])
            ->assertCreated()->assertJsonPath('group.status', 'open')->assertJsonPath('group.isOwner', true)
            ->assertJsonPath('group.totals.grouped', false)->assertJsonPath('group.totals.savingBdt', 0)->json('group');
        $code = $g['code'];
        $this->assertStringEndsWith('/group/'.$code, $g['shareUrl']);

        // Another store's item is refused.
        $friend = $this->shopper(['phone' => '+8801911111111', 'name' => 'Karim Ali']);
        $this->postJson("/api/v1/groups/{$code}/join", ['items' => [['productId' => 'GF1', 'qty' => 1]]])->assertStatus(422);

        $r = $this->postJson("/api/v1/groups/{$code}/join", ['items' => [['productId' => 'GB1', 'qty' => 2]], 'address' => $this->addressPayload(), 'payment' => 'nagad'])
            ->assertOk()->assertJsonCount(2, 'group.members')->assertJsonPath('group.members.1.isYou', true)->assertJsonPath('group.members.1.name', 'Karim')
            ->assertJsonPath('group.totals.grouped', true);
        $pricing = app(PricingService::class);
        $solo = $pricing->shipping([['unit_bdt' => $this->bdt($a), 'qty' => 1, 'weight_kg' => 0.4]])
            + $pricing->shipping([['unit_bdt' => $this->bdt($b), 'qty' => 2, 'weight_kg' => 0.4]]);
        $together = $pricing->shipping([['unit_bdt' => $this->bdt($a), 'qty' => 1, 'weight_kg' => 0.4], ['unit_bdt' => $this->bdt($b), 'qty' => 2, 'weight_kg' => 0.4]]);
        $r->assertJsonPath('group.totals.soloShippingBdt', $solo)->assertJsonPath('group.totals.groupShippingBdt', $together);
        $this->assertGreaterThan(0, $r->json('group.totals.eachSavesBdt'));
        $friendSaving = $r->json('group.you.savingBdt');

        // Public view without a token.
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/groups/{$code}")->assertOk()->assertJsonPath('group.you', null)->assertJsonCount(2, 'group.members');

        // Only the owner can close; the owner cannot leave.
        Sanctum::actingAs($friend);
        $this->postJson("/api/v1/groups/{$code}/close")->assertForbidden();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/groups/{$code}/leave")->assertStatus(422)->assertJsonPath('code', 'owner_cannot_leave');

        $closed = $this->postJson("/api/v1/groups/{$code}/close")->assertOk()->assertJsonPath('placed', 2)->assertJsonPath('group.status', 'closed');
        $group = GroupBuy::query()->where('code', $code)->first();
        $orders = Order::query()->where('group_id', $group->id)->get()->keyBy('user_id');
        $this->assertCount(2, $orders);
        $fo = $orders[$friend->id];
        $this->assertEquals($friendSaving, $fo->group_discount_bdt);
        $this->assertEquals($fo->subtotal_bdt + $fo->shipping_bdt - $friendSaving, $fo->total_bdt);
        $this->assertSame('nagad', $fo->payment_method);
        $this->assertSame($fo->total_bdt, (float) $fo->payments()->first()->amount_bdt); // pays the discounted total
        $this->getJson('/api/v1/orders/'.$orders[$owner->id]->id)->assertJsonPath('order.groupCode', $code);
        $this->postJson("/api/v1/groups/{$code}/join", ['items' => []])->assertStatus(409);
        $this->getJson('/api/v1/groups')->assertOk()->assertJsonPath('items.0.code', $code);
    }

    public function test_single_member_proceeds_without_discount_via_scheduled_closer(): void
    {
        $this->product('GA1', 1000);
        $owner = $this->shopper();
        $this->address($owner);
        $code = $this->postJson('/api/v1/groups', ['market' => 'amazon', 'hours' => 48, 'items' => [['productId' => 'GA1', 'qty' => 1]]])->json('group.code');
        // A friend joined and left again.
        $friend = $this->shopper(['phone' => '+8801911111111']);
        $this->postJson("/api/v1/groups/{$code}/join", ['items' => [['productId' => 'GA1', 'qty' => 1]]])->assertOk();
        $this->postJson("/api/v1/groups/{$code}/leave")->assertOk();

        GroupBuy::query()->where('code', $code)->update(['closes_at' => now()->subMinute()]);
        (new CloseDueGroups)->handle(app(GroupBuyService::class));

        $g = GroupBuy::query()->where('code', $code)->first();
        $this->assertSame('closed', $g->status);
        $this->assertStringContainsString('only one member', $g->close_note);
        $o = Order::query()->where('group_id', $g->id)->sole();
        $this->assertEquals(0, $o->group_discount_bdt);
        $this->assertSame($owner->id, $o->user_id);
        $this->assertSame(0, Order::query()->where('user_id', $friend->id)->count());

        // Nobody with items → cancelled.
        Sanctum::actingAs($owner);
        $empty = $this->postJson('/api/v1/groups', ['market' => 'amazon', 'hours' => 24])->json('group.code');
        GroupBuy::query()->where('code', $empty)->update(['closes_at' => now()->subMinute()]);
        (new CloseDueGroups)->handle(app(GroupBuyService::class));
        $this->assertSame('cancelled', GroupBuy::query()->where('code', $empty)->value('status'));
    }

    public function test_feature_switch(): void
    {
        $this->shopper();
        Features::set('group_orders', false);
        $this->postJson('/api/v1/groups', ['market' => 'amazon'])->assertNotFound()->assertJsonPath('feature', 'group_orders');
        $this->assertSame(0, User::query()->first()->orders()->count());
    }
}
