<?php

namespace Tests\Feature\Growth;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\User;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class GrowthFeatureSwitchTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    public function test_routes_404_when_features_are_off(): void
    {
        $this->shopper();
        Features::set('affiliate', false);
        Features::set('referral', false);
        Features::set('share', false);

        $this->getJson('/api/v1/affiliate/me')->assertNotFound()->assertJsonPath('feature', 'affiliate');
        $this->postJson('/api/v1/r/click', ['code' => 'NADIA10'])->assertNotFound();
        $this->getJson('/api/v1/referrals/me')->assertNotFound()->assertJsonPath('feature', 'referral');
        $this->postJson('/api/v1/wishlist/share')->assertNotFound()->assertJsonPath('feature', 'share');

        Features::set('referral', true);
        $this->getJson('/api/v1/referrals/me')->assertOk();
    }

    public function test_no_commission_while_affiliate_is_off(): void
    {
        $u = User::query()->create(['name' => 'Nadia', 'phone' => '+8801911111111']);
        Affiliate::query()->create(['user_id' => $u->id, 'code' => 'NADIA10', 'status' => 'approved']);
        Features::set('affiliate', false);
        $order = $this->placeOrder($this->shopper(), [$this->product('A1', 1000)], ['ref' => 'NADIA10']);

        $this->assertNull($order->refresh()->affiliate_id);
        $this->assertSame(0, AffiliateCommission::query()->count());
    }
}
