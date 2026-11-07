<?php

namespace Tests\Feature\Growth;

use App\Filament\Pages\GrowthSettings;
use App\Filament\Resources\AffiliateCommissions\AffiliateCommissionResource;
use App\Filament\Resources\AffiliateCommissions\Pages\ListAffiliateCommissions;
use App\Filament\Resources\AffiliatePayouts\AffiliatePayoutResource;
use App\Filament\Resources\AffiliatePayouts\Pages\ListAffiliatePayouts;
use App\Filament\Resources\Affiliates\AffiliateResource;
use App\Filament\Resources\Affiliates\Pages\ListAffiliates;
use App\Filament\Widgets\TopAffiliates;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Category;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GrowthAdminTest extends TestCase
{
    use RefreshDatabase;

    private Affiliate $aff;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = new User;
        $admin->forceFill(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();
        $this->actingAs($admin);

        $u = User::query()->create(['name' => 'Nadia Promoter', 'phone' => '+8801911111111']);
        $this->aff = Affiliate::query()->create(['user_id' => $u->id, 'code' => 'NADIA10', 'status' => 'pending', 'payout_method' => 'bkash', 'payout_number' => '+8801911111111']);
        $buyer = User::query()->create(['name' => 'Buyer', 'phone' => '+8801711000000']);
        Order::query()->create(['id' => 'SR-TEST01', 'user_id' => $buyer->id, 'status' => 'delivered', 'payment_method' => 'bkash', 'payment_status' => 'paid',
            'subtotal_bdt' => 5000, 'shipping_bdt' => 200, 'total_bdt' => 5200, 'address' => ['name' => 'B', 'phone' => '+8801711000000'],
            'fx_rate' => 1.42, 'affiliate_id' => $this->aff->id]);
        Category::query()->create(['market' => 'amazon', 'slug' => 'electronics', 'name' => 'Electronics']);
    }

    public function test_pages_render(): void
    {
        AffiliateCommission::query()->create(['affiliate_id' => $this->aff->id, 'order_id' => 'SR-TEST01', 'base_bdt' => 5000, 'rate' => 3, 'amount_bdt' => 150, 'status' => 'pending']);
        $this->get(AffiliateResource::getUrl('index'))->assertOk()->assertSee('NADIA10');
        $this->get(AffiliateResource::getUrl('edit', ['record' => $this->aff]))->assertOk();
        $this->get(AffiliateCommissionResource::getUrl('index'))->assertOk()->assertSee('SR-TEST01');
        $this->get(AffiliatePayoutResource::getUrl('index'))->assertOk();
        $this->get(GrowthSettings::getUrl())->assertOk()->assertSee('Cookie window')->assertSee('Electronics');
        Livewire::test(TopAffiliates::class)->assertOk()->assertSee('NADIA10');
    }

    public function test_approve_affiliate_bulk_commissions_and_mark_payout_paid(): void
    {
        Livewire::test(ListAffiliates::class)->callTableAction('approve', $this->aff);
        $this->assertSame('approved', $this->aff->refresh()->status);

        $c = AffiliateCommission::query()->create(['affiliate_id' => $this->aff->id, 'order_id' => 'SR-TEST01', 'base_bdt' => 5000, 'rate' => 3, 'amount_bdt' => 150, 'status' => 'pending']);
        Livewire::test(ListAffiliateCommissions::class)->callTableBulkAction('approve', [$c]);
        $this->assertSame('approved', $c->refresh()->status);

        $p = AffiliatePayout::query()->create(['affiliate_id' => $this->aff->id, 'amount_bdt' => 150, 'method' => 'bkash', 'number' => '+8801911111111', 'status' => 'requested']);
        $c->update(['payout_id' => $p->id]);
        Livewire::test(ListAffiliatePayouts::class)->callTableAction('markPaid', $p, ['reference' => 'TRX99']);
        $this->assertSame('paid', $p->refresh()->status);
        $this->assertSame('TRX99', $p->reference);
        $this->assertSame('paid', $c->refresh()->status);
    }

    public function test_settings_save(): void
    {
        Livewire::test(GrowthSettings::class)
            ->set('data.affiliate__cookie_days', 45)
            ->set('data.affiliate__rates__electronics', 6)
            ->set('data.referral__inviter_points', 250)
            ->call('save')->assertHasNoErrors();
        $this->assertEquals(45, Setting::get('affiliate.cookie_days'));
        $this->assertEquals(6, Setting::get('affiliate.rates.electronics'));
        $this->assertEquals(250, Setting::get('referral.inviter_points'));
        $this->assertSame(6.0, \App\Services\Growth\GrowthSettings::rate('electronics'));
        $this->assertSame(3.0, \App\Services\Growth\GrowthSettings::rate('toys'));
    }
}
