<?php

namespace Tests\Feature\Growth;

use App\Models\PointsLedger;
use App\Models\ReferralReward;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Commerce\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    private function signUp(string $phone, string $name, ?string $invite): User
    {
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone])->assertOk()->json('demoCode');
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => $phone, 'code' => $code, 'name' => $name, 'invite' => $invite])
            ->assertOk()->assertJsonPath('isNew', true);

        return User::query()->where('name', $name)->firstOrFail();
    }

    public function test_referral_points_on_first_delivered_order(): void
    {
        $inviter = $this->shopper();
        $r = $this->getJson('/api/v1/referrals/me')->assertOk()->assertJsonPath('invited', 0)->assertJsonPath('rules.inviterPoints', 200);
        $code = $r->json('code');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{7}$/', $code);
        $this->assertStringEndsWith('/?invite='.$code, $r->json('link'));

        $friend = $this->signUp('01812345678', 'Karim Uddin', strtolower($code));
        $this->assertSame($inviter->id, (int) $friend->referred_by);
        $inviterBefore = $inviter->refresh()->points_balance;
        $friendBefore = $friend->points_balance; // signup bonus

        Sanctum::actingAs($friend);
        $friend->addresses()->create(['name' => 'Karim', 'phone' => '+8801812345678', 'division' => 'Dhaka', 'district' => 'Dhaka',
            'area' => 'Mirpur', 'line' => 'Road 9', 'is_default' => true]);
        $o1 = $this->placeOrder($friend, [$this->product('A1', 1000)]);
        $o2 = $this->placeOrder($friend, [$this->product('A2', 1000)]);
        app(OrderWorkflow::class)->transition($o1, 'delivered', 'admin');
        app(OrderWorkflow::class)->transition($o2, 'delivered', 'admin');

        $this->assertSame(1, ReferralReward::query()->count());
        $this->assertSame($inviterBefore + 200, $inviter->refresh()->points_balance);
        $earned = $o1->refresh()->points_earned + $o2->refresh()->points_earned;
        $this->assertSame($friendBefore + 100 + $earned, $friend->refresh()->points_balance);
        $this->assertStringContainsString("Karim's first order {$o1->id}", PointsLedger::query()->where('user_id', $inviter->id)->latest('id')->value('note'));

        Sanctum::actingAs($inviter);
        $this->getJson('/api/v1/referrals/me')->assertJsonPath('invited', 1)->assertJsonPath('rewarded', 1)
            ->assertJsonPath('pointsEarned', 200)->assertJsonPath('friends.0.name', 'Karim U.')->assertJsonPath('friends.0.status', 'rewarded');
    }

    public function test_no_reward_without_delivery_or_with_bad_code(): void
    {
        $inviter = $this->shopper();
        $code = $this->getJson('/api/v1/referrals/me')->json('code');
        $friend = $this->signUp('01812345678', 'Karim Uddin', $code);
        $o = $this->placeOrder($friend, [$this->product('A1', 1000)]);
        app(OrderWorkflow::class)->cancel($o, 'admin');
        $this->assertSame(0, ReferralReward::query()->count());

        $other = $this->signUp('01912345678', 'Lina Akter', 'ZZZZZZZ');
        $this->assertNull($other->referred_by);
        $this->assertNotNull($inviter);
    }

    public function test_public_wishlist(): void
    {
        $u = $this->shopper(['name' => 'Rahim Uddin']);
        $this->product('A1');
        $this->product('A2');
        WishlistItem::query()->create(['user_id' => $u->id, 'product_id' => 'A1']);
        WishlistItem::query()->create(['user_id' => $u->id, 'product_id' => 'A2']);

        $r = $this->postJson('/api/v1/wishlist/share')->assertOk();
        $code = $r->json('code');
        $this->assertStringEndsWith('/w/'.$code, $r->json('url'));
        $this->assertSame($code, $this->postJson('/api/v1/wishlist/share')->json('code')); // stable

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/wishlists/'.$code)->assertOk()->assertJsonPath('owner.name', 'Rahim')
            ->assertJsonPath('count', 2)->assertJsonPath('items.0.id', 'A2')->assertJsonMissing(['phone' => $u->phone]);
        $this->getJson('/api/v1/wishlists/nope1234')->assertNotFound();
    }
}
