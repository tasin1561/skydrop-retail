<?php

namespace Tests\Feature\Commerce;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\CouponRules;
use App\Services\Commerce\OrderWorkflow;
use App\Support\Features;
use App\Support\Rbac\PermissionResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin-controlled coupon rules: per customer, first order, store, schedule, allowed phones, featured promos. */
class CouponControlsTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    private function quote(array $items, string $code)
    {
        return $this->postJson('/api/v1/checkout/quote', ['items' => $items, 'coupon' => $code]);
    }

    private function coupon(array $attrs): Coupon
    {
        return Coupon::query()->create(array_merge(['type' => 'fixed', 'value' => 100, 'active' => true], $attrs));
    }

    public function test_per_customer_limit_is_enforced_and_released_on_cancel(): void
    {
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        $item = [['productId' => 'A1', 'qty' => 1]];

        $first = $this->placeOrder($u, [$p], ['coupon' => 'SKYDROP10']);
        $this->assertSame(Coupon::query()->where('code', 'SKYDROP10')->value('id'), $first->coupon_id);

        $this->quote($item, 'SKYDROP10')->assertStatus(422)->assertJsonPath('errors.coupon.0', 'You have already used this coupon.');
        try {
            $this->placeOrder($u, [$p], ['coupon' => 'SKYDROP10']);
            $this->fail('second use should be refused');
        } catch (CommerceException $e) {
            $this->assertSame('You have already used this coupon.', $e->getMessage());
        }
        $this->assertSame(1, Coupon::query()->where('code', 'SKYDROP10')->value('used'));

        // A different shopper is unaffected.
        $this->shopper(['phone' => '+8801812345678']);
        $this->quote($item, 'SKYDROP10')->assertOk();

        // Cancelling gives the use back.
        app(OrderWorkflow::class)->cancel($first->refresh());
        $this->assertSame(0, Coupon::query()->where('code', 'SKYDROP10')->value('used'));
        Sanctum::actingAs($u);
        $this->quote($item, 'SKYDROP10')->assertOk();

        // Blank limit = unlimited.
        Coupon::query()->where('code', 'SKYDROP10')->update(['per_customer_limit' => null]);
        $this->placeOrder($u, [$p], ['coupon' => 'SKYDROP10']);
        $this->placeOrder($u, [$p], ['coupon' => 'SKYDROP10']);
        $this->assertSame(2, Coupon::query()->where('code', 'SKYDROP10')->value('used'));
    }

    public function test_claim_rechecks_per_customer_limit_inside_the_order(): void
    {
        // Simulates two parallel orders that both passed the quote: the second claim must fail under the lock.
        $u = $this->shopper();
        $c = Coupon::query()->where('code', 'SKYDROP10')->first();
        $mk = fn (string $id) => Order::query()->create(['id' => $id, 'user_id' => $u->id, 'status' => 'placed', 'payment_method' => 'bkash',
            'subtotal_bdt' => 1000, 'shipping_bdt' => 0, 'total_bdt' => 1000, 'fx_rate' => 1.42, 'address' => [], 'coupon_id' => $c->id]);
        $a = $mk('SR-AAAAA1');
        app(CouponRules::class)->claimFor($c, $u, $a);
        $b = $mk('SR-AAAAA2');
        $this->expectException(CommerceException::class);
        app(CouponRules::class)->claimFor($c, $u, $b);
    }

    public function test_concurrency_safe_claim_still_respects_max_uses(): void
    {
        $c = $this->coupon(['code' => 'TWO', 'max_uses' => 2]);
        $this->assertTrue(Coupon::claim($c->id));
        $this->assertTrue(Coupon::claim($c->id));
        $this->assertFalse(Coupon::claim($c->id));
        $this->assertSame(2, $c->refresh()->used);
        Coupon::release($c->id);
        $this->assertTrue(Coupon::claim($c->id));
    }

    public function test_first_order_only(): void
    {
        $this->coupon(['code' => 'WELCOME', 'first_order_only' => true]);
        $p = $this->product('A1', 1000);
        $item = [['productId' => 'A1', 'qty' => 1]];

        $this->quote($item, 'WELCOME')->assertStatus(422)->assertJsonPath('errors.coupon.0', 'Sign in to use this coupon.');

        $u = $this->shopper();
        $this->quote($item, 'WELCOME')->assertOk()->assertJsonPath('coupon.firstOrderOnly', true)->assertJsonPath('discount', 100);
        $this->placeOrder($u, [$p]); // any earlier order (without the coupon) counts
        $this->quote($item, 'WELCOME')->assertStatus(422)->assertJsonPath('errors.coupon.0', 'This coupon is only for your first order.');
    }

    public function test_allowed_phones(): void
    {
        $this->coupon(['code' => 'VIP', 'allowed_phones' => ['+8801712345678']]);
        $this->product('A1', 1000);
        $item = [['productId' => 'A1', 'qty' => 1]];
        $this->quote($item, 'VIP')->assertStatus(422)->assertJsonPath('errors.coupon.0', 'Sign in to use this coupon.');
        $this->shopper(['phone' => '+8801912345678']);
        $this->quote($item, 'VIP')->assertStatus(422)->assertJsonPath('errors.coupon.0', 'This coupon is not available for your account.');
        $this->shopper(); // +8801712345678
        $this->quote($item, 'VIP')->assertOk();
    }

    public function test_store_restriction_only_discounts_that_store(): void
    {
        $a = $this->product('A1', 1000);
        $f = $this->product('F1', 2000, 'flipkart');
        $this->coupon(['code' => 'STYLE10', 'type' => 'percent_items', 'value' => 10, 'store' => 'flipkart']);
        $this->coupon(['code' => 'STYLESHIP', 'type' => 'free_shipping', 'value' => 0, 'store' => 'flipkart']);
        $this->coupon(['code' => 'STYLEMIN', 'store' => 'flipkart', 'min_subtotal_bdt' => 100000]);

        $this->quote([['productId' => 'A1', 'qty' => 1]], 'STYLE10')->assertStatus(422)
            ->assertJsonPath('errors.coupon.0', 'This coupon only works on Flipkart (flipkart.com) items.');

        $both = [['productId' => 'A1', 'qty' => 1], ['productId' => 'F1', 'qty' => 1]];
        $r = $this->quote($both, 'STYLE10')->assertOk()->assertJsonPath('coupon.store', 'flipkart');
        $this->assertSame((int) round($this->bdt($f) * 0.1), $r->json('itemDiscount'));

        $r = $this->quote($both, 'STYLESHIP')->assertOk();
        $fShip = collect($r->json('groups'))->firstWhere('market', 'flipkart')['shipping'];
        $this->assertSame($fShip, $r->json('shipDiscount'));
        $this->assertLessThan($r->json('shipping'), $r->json('shipDiscount') + 1); // amazon shipping still charged

        $this->quote($both, 'STYLEMIN')->assertStatus(422);
        $this->assertStringContainsString('from Flipkart', $this->quote($both, 'STYLEMIN')->json('errors.coupon.0'));
        $this->assertGreaterThan(0, $this->bdt($a));
    }

    public function test_schedule_window(): void
    {
        Carbon::setTestNow('2026-11-07 06:00:00'); // 12:00 Dhaka
        $this->product('A1', 1000);
        $item = [['productId' => 'A1', 'qty' => 1]];
        $c = $this->coupon(['code' => 'EID', 'starts_at' => now()->addDay(), 'expires_at' => now()->addDays(3)]);

        $this->quote($item, 'EID')->assertStatus(422)->assertJsonPath('errors.coupon.0', 'This coupon starts on 8 Nov, 12:00 PM (Bangladesh time).');
        $this->assertFalse(Coupon::claim($c->id));

        Carbon::setTestNow(now()->addDays(2));
        $this->quote($item, 'EID')->assertOk()->assertJsonPath('coupon.endsAt', $c->expires_at->toIso8601String());

        Carbon::setTestNow(now()->addDays(2));
        $this->quote($item, 'EID')->assertStatus(422)->assertJsonPath('errors.coupon.0', 'This coupon has expired.');

        $this->coupon(['code' => 'OFF', 'active' => false]);
        $this->quote($item, 'OFF')->assertStatus(422)->assertJsonPath('errors.coupon.0', 'This coupon is no longer available.');
        $this->coupon(['code' => 'GONE', 'max_uses' => 1, 'used' => 1]);
        $this->quote($item, 'GONE')->assertStatus(422)->assertJsonPath('errors.coupon.0', 'This coupon has been fully used.');
        Carbon::setTestNow();
    }

    public function test_featured_endpoint(): void
    {
        $this->coupon(['code' => 'STYLE', 'store' => 'flipkart', 'featured' => true, 'promo_text_en' => 'Style deal', 'promo_text_bn' => 'স্টাইল অফার', 'max_uses' => 50]);
        $this->coupon(['code' => 'SECRET', 'featured' => true, 'allowed_phones' => ['+8801712345678']]);
        $this->coupon(['code' => 'LATER', 'featured' => true, 'starts_at' => now()->addDay()]);
        $this->coupon(['code' => 'DONE', 'featured' => true, 'expires_at' => now()->subMinute()]);
        $this->coupon(['code' => 'USEDUP', 'featured' => true, 'max_uses' => 1, 'used' => 1]);

        $r = $this->getJson('/api/v1/coupons/featured')->assertOk();
        $codes = collect($r->json('items'))->pluck('code')->sort()->values()->all();
        $this->assertSame(['SKYDROP10', 'STYLE'], $codes); // FREESHIP is not featured
        $style = collect($r->json('items'))->firstWhere('code', 'STYLE');
        $this->assertSame(['en' => 'Style deal', 'bn' => 'স্টাইল অফার'], $style['promoText']);
        $this->assertSame('flipkart', $style['store']);
        foreach (['max_uses', 'maxUses', 'used', 'allowed_phones', 'allowedPhones', 'per_customer_limit', 'id'] as $secret) {
            $this->assertArrayNotHasKey($secret, $style);
        }

        $this->assertSame(['SKYDROP10'], collect($this->getJson('/api/v1/coupons/featured?store=amazon')->json('items'))->pluck('code')->all());

        Features::set('coupons', false);
        $this->getJson('/api/v1/coupons/featured')->assertOk()->assertExactJson(['items' => []]);
    }

    public function test_admin_form_saves_new_fields(): void
    {
        $staff = new User;
        $staff->forceFill(['name' => 'QA marketing', 'email' => 'mk@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'support'])->save();
        $staff->syncRoles(Role::query()->where('slug', 'marketing')->pluck('id')->all());
        app(PermissionResolver::class)->flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($staff);

        Livewire::test(CreateCoupon::class)->fillForm([
            'code' => 'eid25', 'type' => 'percent_items', 'value' => 25, 'max_discount_bdt' => 500, 'min_subtotal_bdt' => 2000,
            'store' => 'bigbasket', 'starts_at' => '2026-12-01 10:00', 'expires_at' => '2026-12-05 23:59',
            'max_uses' => 300, 'per_customer_limit' => 2, 'first_order_only' => true,
            'allowed_phones' => ['01712345678', '+8801812345678'],
            'featured' => true, 'promo_text_en' => 'Eid: 25% off groceries', 'promo_text_bn' => 'ঈদে মুদিতে ২৫% ছাড়',
        ])->call('create')->assertHasNoFormErrors();

        $c = Coupon::query()->where('code', 'EID25')->firstOrFail();
        $this->assertSame('bigbasket', $c->store);
        $this->assertSame(2, $c->per_customer_limit);
        $this->assertTrue($c->first_order_only);
        $this->assertTrue($c->featured);
        $this->assertSame(['+8801712345678', '+8801812345678'], $c->allowed_phones);
        $this->assertSame('ঈদে মুদিতে ২৫% ছাড়', $c->promo_text_bn);
        $this->assertSame('2026-12-01 04:00:00', $c->starts_at->utc()->format('Y-m-d H:i:s')); // entered in Dhaka time

        // New coupons default to one use per customer; bad phone numbers and an end before the start are rejected.
        Livewire::test(CreateCoupon::class)->fillForm(['code' => 'DEF1', 'type' => 'fixed', 'value' => 50])->call('create')->assertHasNoFormErrors();
        $this->assertSame(1, Coupon::query()->where('code', 'DEF1')->value('per_customer_limit'));
        Livewire::test(CreateCoupon::class)->fillForm(['code' => 'BADPH', 'type' => 'fixed', 'value' => 50, 'allowed_phones' => ['12345']])
            ->call('create')->assertHasFormErrors();
        Livewire::test(CreateCoupon::class)->fillForm(['code' => 'BADDT', 'type' => 'fixed', 'value' => 50,
            'starts_at' => '2026-12-05 10:00', 'expires_at' => '2026-12-01 10:00'])->call('create')->assertHasFormErrors(['expires_at']);

        // Blank per-customer = unlimited on edit.
        Livewire::test(EditCoupon::class, ['record' => $c->getKey()])->fillForm(['per_customer_limit' => null, 'allowed_phones' => []])
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull($c->refresh()->per_customer_limit);
        $this->assertNull($c->allowed_phones);
    }
}
