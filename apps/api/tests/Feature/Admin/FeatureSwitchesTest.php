<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\FeatureSwitches;
use App\Models\Marketplace;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Catalog\CatalogService;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class FeatureSwitchesTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    private function superAdmin(): User
    {
        $user = new User;
        $user->forceFill(['name' => 'Boss', 'email' => 'boss@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true])->save();
        $user->roles()->sync([Role::query()->where('slug', 'super_admin')->value('id')]);

        return $user;
    }

    public function test_page_toggles_a_switch_records_meta_and_the_route_middleware_404s(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(FeatureSwitches::class)
            ->assertSee('Reviews')
            ->callAction('toggle', arguments: ['key' => 'reviews'])
            ->assertSet('states.reviews', false)
            ->assertSee('Turned OFF by Boss');

        $this->assertFalse(Features::enabled('reviews'));
        $this->assertSame('Boss', Setting::get('features._meta.reviews')['by']);
        $this->getJson('/api/v1/products/A1/reviews')->assertNotFound()->assertJsonPath('error', 'feature_disabled');

        Livewire::test(FeatureSwitches::class)->callAction('group', arguments: ['section' => 'Payments', 'on' => false]);
        $this->assertFalse(Features::enabled('pay.bkash'));
        $this->assertFalse(Features::enabled('coupons'));
    }

    public function test_features_page_needs_features_manage(): void
    {
        $manager = new User;
        $manager->forceFill(['name' => 'M', 'email' => 'm@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'manager'])->save();
        $this->actingAs($manager)->get(FeatureSwitches::getUrl())->assertForbidden();
    }

    public function test_checkout_off_returns_503(): void
    {
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        Features::set('checkout', false);

        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1]]])
            ->assertStatus(503)->assertJsonPath('feature', 'checkout');
        $this->postJson('/api/v1/checkout/verify', ['items' => [['productId' => 'A1', 'qty' => 1]]])->assertStatus(503);
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]],
            'addressId' => $this->address($u)->id, 'payment' => 'bkash'])->assertStatus(503);
        $this->getJson('/api/v1/checkout/options')->assertOk()->assertJsonPath('checkout', false);
    }

    public function test_signups_off_rejects_new_users_only(): void
    {
        Features::set('signups', false);
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01799999999', 'code' => '123456', 'name' => 'New'])
            ->assertForbidden()->assertJsonPath('feature', 'signups');

        User::query()->create(['name' => 'Old', 'phone' => '+8801788888888']);
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01788888888', 'code' => '123456'])->assertStatus(422); // wrong/expired code, not 403
    }

    public function test_disabled_payment_method_is_rejected_and_hidden(): void
    {
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        Features::set('pay.nagad', false);

        $this->getJson('/api/v1/checkout/options')->assertOk()->assertJsonMissing(['id' => 'nagad'])->assertJsonFragment(['id' => 'bkash']);
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]],
            'addressId' => $this->address($u)->id, 'payment' => 'nagad'])
            ->assertStatus(422)->assertJsonPath('error', 'payment_method_disabled');
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]],
            'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])->assertCreated();
    }

    public function test_points_off_are_ignored_with_a_notice(): void
    {
        $this->shopper();
        $this->product('A1', 1000);
        Features::set('points', false);

        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1]], 'redeemPoints' => 100])
            ->assertOk()->assertJsonPath('pointsRedeemed', 0)->assertJsonPath('notices.0.feature', 'points');
    }

    public function test_mobile_app_switch_blocks_mobile_clients(): void
    {
        Features::set('mobile_app', false);
        $this->getJson('/api/v1/health', ['X-Client' => 'mobile'])->assertStatus(503)->assertJsonPath('feature', 'mobile_app');
        $this->getJson('/api/v1/health')->assertOk();
    }

    public function test_store_switch_and_marketplace_stay_in_sync_and_live_fetch_is_global(): void
    {
        $m = Marketplace::query()->create(['id' => 'flipkart', 'name' => 'Style Bazaar', 'source' => 'flipkart.com', 'enabled' => true, 'live_fetch' => true]);

        Features::set('store.flipkart', false);
        $this->assertFalse($m->refresh()->enabled);

        $m->update(['enabled' => true]);
        $this->assertTrue(Features::enabled('store.flipkart'), 'store sync');

        $this->assertTrue(app(CatalogService::class)->isLive('flipkart'), 'live');
        Features::set('live_fetch', false);
        $this->assertFalse(app(CatalogService::class)->isLive('flipkart'));
    }
}
