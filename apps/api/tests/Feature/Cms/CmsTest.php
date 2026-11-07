<?php

namespace Tests\Feature\Cms;

use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\FlashSales\FlashSaleResource;
use App\Filament\Resources\FlashSales\Schemas\FlashSaleForm;
use App\Filament\Resources\RestrictedRules\Pages\ListRestrictedRules;
use App\Filament\Resources\RestrictedRules\RestrictedRuleResource;
use App\Models\Banner;
use App\Models\FlashSale;
use App\Models\ProductReview;
use App\Models\RestrictedRule;
use App\Models\User;
use App\Services\Cms\CmsCache;
use App\Services\Cms\RestrictionService;
use App\Services\Commerce\OrderWorkflow;
use App\Services\PricingService;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    private function sale(array $items, array $attrs = []): FlashSale
    {
        $s = FlashSale::query()->create(array_merge(['title' => 'Midnight flash', 'store' => 'all', 'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(3), 'active' => true], $attrs));
        foreach ($items as $id => $off) {
            $s->items()->create(['product_id' => $id] + $off);
        }

        return $s;
    }

    public function test_cms_home_returns_live_banners_and_live_and_upcoming_sales(): void
    {
        $this->product('A1', 1000);
        $this->product('F1', 800, 'flipkart', 'fashion');
        Banner::query()->create(['title' => 'Eid picks', 'placement' => 'hero', 'store' => 'all', 'image_url' => 'https://cdn.example.com/eid.jpg', 'sort' => 2]);
        Banner::query()->create(['title' => 'Saree week', 'placement' => 'hero', 'store' => 'flipkart', 'sort' => 1]);
        Banner::query()->create(['title' => 'Expired', 'placement' => 'strip', 'ends_at' => now()->subDay()]);
        Banner::query()->create(['title' => 'Later', 'placement' => 'strip', 'starts_at' => now()->addDay()]);
        Banner::query()->create(['title' => 'Off', 'placement' => 'hero', 'active' => false]);
        $this->sale(['A1' => ['off_pct' => 5]]);
        $this->sale(['F1' => ['off_bdt' => 50]], ['title' => 'Tomorrow', 'store' => 'flipkart', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2)]);
        $this->sale(['A1' => ['off_pct' => 9]], ['title' => 'Old', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);

        $r = $this->getJson('/api/v1/cms/home')->assertOk();
        $this->assertSame(['Saree week', 'Eid picks'], array_column($r->json('banners'), 'title'));
        $this->assertSame(['Midnight flash', 'Tomorrow'], array_column($r->json('flashSales'), 'title'));
        $r->assertJsonPath('flashSales.0.status', 'live')->assertJsonPath('flashSales.1.status', 'upcoming')
            ->assertJsonPath('flashSales.0.items.0.product.id', 'A1')
            ->assertJsonPath('banners.1.image', 'https://cdn.example.com/eid.jpg');
        $regular = $r->json('flashSales.0.items.0.regularBdt');
        $this->assertSame((int) round($regular * 0.05), $r->json('flashSales.0.items.0.extraOffBdt'));
        $this->assertSame($regular - $r->json('flashSales.0.items.0.extraOffBdt'), $r->json('flashSales.0.items.0.product.priceBdt'));
        $r->assertJsonPath('flashSales.0.items.0.product.flashSale.extraOffBdt', $r->json('flashSales.0.items.0.extraOffBdt'));
        $this->assertNull($r->json('flashSales.1.items.0.product.flashSale')); // upcoming: not priced in yet

        $amazon = $this->getJson('/api/v1/cms/home?store=amazon')->assertOk();
        $this->assertSame(['Eid picks'], array_column($amazon->json('banners'), 'title'));
        $this->assertSame(['Midnight flash'], array_column($amazon->json('flashSales'), 'title'));
    }

    public function test_flash_discount_is_applied_in_quote_and_order_and_capped_at_margin(): void
    {
        $u = $this->shopper();
        $this->address($u);
        $p = $this->product('A1', 1000);
        $regular = $this->bdt($p);
        $this->sale(['A1' => ['off_bdt' => 100]], ['max_qty' => 2]);
        $flash = app(PricingService::class)->productBdt($p);
        $this->assertSame($regular - 100, $flash);

        $q = $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 2, 'unitBdt' => $flash]]])->assertOk();
        $q->assertJsonPath('subtotal', $flash * 2)->assertJsonPath('flashDiscount', 200)
            ->assertJsonPath('items.0.unitBdt', $flash)->assertJsonPath('items.0.changed', false)
            ->assertJsonPath('items.0.flashSale.extraOffBdt', 100);

        // Per-order limit.
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 3]]])
            ->assertStatus(422)->assertJsonPath('code', 'flash_limit');

        $o = $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 2, 'unitBdt' => $flash]],
            'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])->assertCreated();
        $o->assertJsonPath('order.items.0.unitBdt', $flash)->assertJsonPath('order.subtotal', $flash * 2);

        // A huge discount is capped at the 10% margin.
        FlashSale::query()->first()->items()->update(['off_bdt' => 999999]);
        CmsCache::flush();
        $cap = (int) floor($regular - $regular / 1.1);
        $this->assertSame($regular - $cap, app(PricingService::class)->productBdt($p));

        // Ended sale: back to the regular price.
        $this->travel(4)->hours();
        $this->assertSame($regular, app(PricingService::class)->productBdt($p->fresh()));
    }

    public function test_store_specific_sale_does_not_discount_other_markets(): void
    {
        $p = $this->product('A1', 1000);
        $this->sale(['A1' => ['off_pct' => 5]], ['store' => 'flipkart']);
        $this->assertSame($this->bdt($p), app(PricingService::class)->productBdt($p));
    }

    public function test_blocked_items_are_rejected_on_order_and_fail_verify(): void
    {
        $u = $this->shopper();
        $this->address($u);
        $knife = $this->product('K1', 900, 'amazon', 'home', ['title' => 'Stainless steel kitchen knife set, 5 pieces']);
        $bank = $this->product('P1', 1500, 'amazon', 'electronics', ['title' => 'Mi 20000mAh Power Bank, fast charging']);

        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'K1', 'qty' => 1, 'unitBdt' => $this->bdt($knife)]],
            'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])
            ->assertStatus(422)->assertJsonPath('code', 'restricted')->assertJsonPath('restricted.0.productId', 'K1');
        $this->assertDatabaseCount('orders', 0);

        $this->postJson('/api/v1/checkout/verify', ['items' => [['productId' => 'K1', 'qty' => 1, 'unitBdt' => $this->bdt($knife)]]])
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('results.0.reason', 'restricted');

        // Warn only: order goes through; quote lists the notice.
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'P1', 'qty' => 1]]])
            ->assertOk()->assertJsonPath('restrictions.0.action', 'warn')->assertJsonPath('restrictions.0.productId', 'P1');
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'P1', 'qty' => 1, 'unitBdt' => $this->bdt($bank)]],
            'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])->assertCreated();
    }

    public function test_surcharge_rule_adds_to_price_and_product_json_shows_restriction(): void
    {
        $u = $this->shopper();
        $p = $this->product('S1', 1000, 'amazon', 'home', ['brand' => 'HeavyCo']);
        RestrictedRule::query()->create(['type' => 'brand', 'pattern' => 'heavyco', 'action' => 'surcharge', 'surcharge_bdt' => 250, 'message' => 'Oversized parcel.']);
        $this->assertSame($this->bdt($p) + 250, app(PricingService::class)->productBdt($p));

        $this->postJson('/api/v1/wishlist', ['productId' => 'S1'])->assertCreated();
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonPath('items.0.restriction.action', 'surcharge')
            ->assertJsonPath('items.0.restriction.message', 'Oversized parcel.')->assertJsonPath('items.0.flashSale', null);
        $this->assertNotNull($u);
    }

    public function test_default_rules_cover_the_common_cases_without_false_positives(): void
    {
        $r = app(RestrictionService::class);
        $cases = [
            'Johnnie Walker Black Label whisky 750ml' => 'block', 'Crystal wine glasses set of 6' => null,
            'DJI Mini 4 drone with camera' => 'warn', 'Car cigarette lighter USB charger' => null, 'Marlboro cigarettes' => 'block',
            'Fortune sunflower oil 5 L jar' => 'warn', 'Dettol handwash 200 ml' => null, 'Organic chia seeds 250g' => null,
            'Tomato vegetable seeds for planting' => 'block', 'Optimum Nutrition whey protein 2 lb' => 'warn',
            'Axe body spray 150ml' => 'block', 'Samsung Galaxy Tab S9 tablet' => null, 'Anker power bank 10000mAh' => 'warn',
        ];
        foreach ($cases as $title => $expected) {
            $this->assertSame($expected, $r->test($title)['action'] ?? null, $title);
        }
    }

    public function test_review_permissions_and_photo_url(): void
    {
        $p = $this->product('A1', 1000);
        $body = ['rating' => 4, 'body' => 'Good quality, as described.', 'photoUrl' => 'https://img.example.com/r.jpg'];

        $this->postJson('/api/v1/products/A1/reviews', $body)->assertUnauthorized();

        $u = $this->shopper();
        $this->getJson('/api/v1/products/A1/reviews')->assertOk()->assertJsonPath('canReview', false);
        $this->postJson('/api/v1/products/A1/reviews', $body)->assertForbidden()->assertJsonPath('code', 'not_a_buyer');

        $o = $this->placeOrder($u, [$p]);
        app(OrderWorkflow::class)->transition($o, 'delivered', 'admin');
        $this->postJson('/api/v1/products/A1/reviews', ['photoUrl' => 'not-a-url'] + $body)->assertStatus(422)->assertJsonValidationErrors('photoUrl');
        $this->postJson('/api/v1/products/A1/reviews', $body)->assertCreated()->assertJsonPath('review.photoUrl', 'https://img.example.com/r.jpg');

        // Another shopper cannot see the pending review; after approval the breakdown counts it.
        ProductReview::query()->update(['status' => 'approved']);
        $this->shopper(['phone' => '+8801812345678']);
        $this->getJson('/api/v1/products/A1/reviews')->assertOk()->assertJsonPath('canReview', false)
            ->assertJsonPath('breakdown.1', ['rating' => 4, 'count' => 1])->assertJsonPath('items.0.verifiedPurchase', true);
    }

    public function test_admin_resources_render_and_title_tester_works(): void
    {
        $admin = new User;
        $admin->forceFill(['name' => 'Admin', 'email' => 'cms-admin@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();
        $this->actingAs($admin);
        $this->product('A1', 1000);
        $sale = $this->sale(['A1' => ['off_pct' => 5]]);
        $banner = Banner::query()->create(['title' => 'Eid picks', 'placement' => 'hero']);

        foreach ([BannerResource::class, FlashSaleResource::class, RestrictedRuleResource::class] as $res) {
            $this->get($res::getUrl('index'))->assertOk();
            $this->get($res::getUrl('create'))->assertOk();
        }
        $this->get(FlashSaleResource::getUrl('edit', ['record' => $sale]))->assertOk()->assertSee('Midnight flash');
        $this->get(BannerResource::getUrl('edit', ['record' => $banner]))->assertOk();

        $this->assertArrayHasKey('A1', FlashSaleForm::search('Test product'));
        $this->assertArrayHasKey('A1', FlashSaleForm::search('A1'));
        $this->assertSame('block', ListRestrictedRules::notifyTest(['title' => 'Bacardi white rum 1L'])['action']);

        Livewire::test(CreateBanner::class)->fillForm(['title' => 'New hero', 'store' => 'amazon', 'placement' => 'hero', 'sort' => 0, 'active' => true])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('banners', ['title' => 'New hero', 'store' => 'amazon']);
    }

    public function test_feature_switches_turn_cms_off(): void
    {
        $u = $this->shopper();
        $this->address($u);
        $p = $this->product('A1', 1000);
        $knife = $this->product('K1', 900, 'amazon', 'home', ['title' => 'Chef knife 8 inch']);
        Banner::query()->create(['title' => 'Eid picks', 'placement' => 'hero']);
        $this->sale(['A1' => ['off_bdt' => 100]]);

        Features::set('flash_sales', false);
        Features::set('banners', false);
        Features::set('restrictions', false);
        Features::set('coupons', false);
        Features::set('reviews', false);
        CmsCache::flush();

        $this->assertSame($this->bdt($p), app(PricingService::class)->productBdt($p));
        $this->getJson('/api/v1/cms/home')->assertOk()->assertJsonCount(0, 'banners')->assertJsonCount(0, 'flashSales')
            ->assertJsonPath('enabled.flashSales', false);
        // Coupons switched off: the code is ignored with a notice (App\Http\Middleware\EnforceFeatureSwitches).
        $this->postJson('/api/v1/checkout/quote', ['items' => [['productId' => 'A1', 'qty' => 1]], 'coupon' => 'SKYDROP10'])
            ->assertOk()->assertJsonPath('notices.0.feature', 'coupons')->assertJsonPath('coupon', null);
        $this->getJson('/api/v1/products/A1/reviews')->assertNotFound()->assertJsonPath('error', 'feature_disabled');
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'K1', 'qty' => 1, 'unitBdt' => $this->bdt($knife)]],
            'addressId' => $u->addresses()->first()->id, 'payment' => 'bkash'])->assertCreated();
    }
}
