<?php

namespace Tests\Feature\Shopping;

use App\Filament\Pages\WaitlistDemand;
use App\Filament\Resources\Bundles\BundleResource;
use App\Filament\Resources\GroupBuys\GroupBuyResource;
use App\Filament\Resources\GroupBuys\Pages\ListGroupBuys;
use App\Filament\Resources\PreorderRules\PreorderRuleResource;
use App\Filament\Resources\ProductMatches\Pages\ListProductMatches;
use App\Filament\Resources\ProductMatches\ProductMatchResource;
use App\Filament\Resources\ProductRequests\Pages\ListProductRequests;
use App\Filament\Resources\ProductRequests\ProductRequestResource;
use App\Models\Bundle;
use App\Models\GroupBuy;
use App\Models\ProductMatch;
use App\Models\ProductRequest;
use App\Models\ProductWatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShoppingAdminTest extends TestCase
{
    use RefreshDatabase, ShoppingHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopping();
    }

    private function staff(): User
    {
        $u = new User;
        $u->forceFill(['name' => 'Admin Staff', 'email' => 'admin@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();

        return $u;
    }

    public function test_admin_pages_render_and_actions_work(): void
    {
        $customer = User::query()->create(['name' => 'Rahim', 'phone' => '+8801712345678']);
        $a = $this->product('AA1', 1000, 'amazon', 'electronics', ['in_stock' => false]);
        $b = $this->product('AB1', 900, 'flipkart');
        $m = ProductMatch::query()->create(['product_id' => 'AA1', 'match_id' => 'AB1', 'method' => 'title', 'score' => 0.7]);
        $bundle = Bundle::query()->create(['slug' => 'kit', 'title' => 'Kit', 'discount_pct' => 5]);
        $bundle->items()->createMany([['product_id' => 'AA1'], ['product_id' => 'AB1']]);
        $g = GroupBuy::query()->create(['code' => 'ABCDEFGH', 'market' => 'amazon', 'owner_id' => $customer->id, 'closes_at' => now()->addDay()]);
        $req = ProductRequest::query()->create(['description' => 'Yonex racket', 'phone' => '+8801712345678']);
        ProductWatch::query()->create(['user_id' => $customer->id, 'product_id' => 'AA1', 'type' => 'back_in_stock', 'active' => true]);

        $this->actingAs($this->staff());
        foreach ([ProductMatchResource::class, BundleResource::class, GroupBuyResource::class, PreorderRuleResource::class, ProductRequestResource::class] as $r) {
            $this->get($r::getUrl('index'))->assertOk();
        }
        $this->get(BundleResource::getUrl('edit', ['record' => $bundle]))->assertOk();
        $this->get(GroupBuyResource::getUrl('view', ['record' => $g]))->assertOk()->assertSee('ABCDEFGH');
        $this->get(ProductRequestResource::getUrl('view', ['record' => $req]))->assertOk()->assertSee('Yonex racket');
        $this->get(WaitlistDemand::getUrl())->assertOk()->assertSee('Test product AA1');

        Livewire::test(ListProductMatches::class)->callTableAction('confirm', $m);
        $this->assertSame('confirmed', $m->refresh()->status);

        Livewire::test(ListProductRequests::class)->set('activeTab', 'new')
            ->callTableAction('sendQuote', $req, ['price' => 5000, 'shipping' => 0, 'title' => 'Yonex Astrox'])->assertHasNoTableActionErrors();
        $this->assertSame('quoted', $req->refresh()->status);
        $this->assertNotNull($req->quote_order_id);

        Livewire::test(ListGroupBuys::class)->callTableAction('cancelGroup', $g);
        $this->assertSame('cancelled', $g->refresh()->status);
    }
}
