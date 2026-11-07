<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\PurchaseQueue;
use App\Filament\Pages\Settings;
use App\Filament\Resources\ApiLogs\ApiLogResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\FxRates\FxRateResource;
use App\Filament\Resources\Marketplaces\MarketplaceResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PointsLedgers\PointsLedgerResource;
use App\Filament\Resources\PricingRules\PricingRuleResource;
use App\Filament\Resources\ProductReviews\ProductReviewResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\ProductWatches\ProductWatchResource;
use App\Filament\Resources\ProviderHealths\ProviderHealthResource;
use App\Filament\Resources\Staff\StaffResource;
use App\Filament\Resources\SupportRequests\SupportRequestResource;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Users\RelationManagers\PointsRelationManager;
use App\Filament\Resources\Users\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Users\RelationManagers\SupportRequestsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Ops;
use App\Filament\Widgets\ApiCallsChart;
use App\Filament\Widgets\LowMarginAlerts;
use App\Filament\Widgets\OrdersByMarketplaceChart;
use App\Filament\Widgets\OrdersByStatusChart;
use App\Filament\Widgets\OrderStatsOverview;
use App\Filament\Widgets\OxylabsBudget;
use App\Filament\Widgets\ProviderHealthTable;
use App\Models\Marketplace;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ProviderHealth;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): User
    {
        if ($role === 'admin' && class_exists(AdminUserSeeder::class)) {
            $this->seed(AdminUserSeeder::class);
            $user = User::query()->where('email', 'admin@skydrop.retail')->first();
            if ($user) {
                return $user;
            }
        }

        $user = new User;
        $user->forceFill([
            'name' => ucfirst($role).' Staff', 'email' => $role.'@skydrop.retail', 'password' => 'secret-password',
            'is_admin' => true, 'role' => $role,
        ])->save();

        return $user;
    }

    private function customer(): User
    {
        $user = new User;
        $user->forceFill(['name' => 'Rahim Uddin', 'phone' => '+8801711000000', 'is_admin' => false])->save();

        return $user;
    }

    private function seedData(): Order
    {
        Marketplace::query()->create(['id' => 'amazon', 'name' => 'Mega Store', 'source' => 'amazon.in', 'enabled' => true]);
        Marketplace::query()->create(['id' => 'flipkart', 'name' => 'Style Bazaar', 'source' => 'flipkart.com', 'enabled' => false]);
        Product::query()->create(['id' => 'B0TEST0001', 'market' => 'amazon', 'category' => 'electronics', 'title' => 'Wireless earbuds', 'price_inr' => 1999, 'mrp_inr' => 2999, 'image' => 'https://example.com/a.jpg']);
        ProductDetail::query()->create(['product_id' => 'B0TEST0001', 'images' => ['https://example.com/b.jpg'],
            'variants' => ['color' => [['value' => 'Black', 'asin' => 'B0TEST0001'], ['value' => 'White', 'asin' => 'B0TEST0002']]],
            'specs' => ['Battery' => '30 h', 'Weight' => '45 g'], 'bullets' => ['ANC']]);
        DB::table('api_logs')->insert(['provider' => 'oxylabs', 'type' => 'product', 'market' => 'amazon', 'key' => 'B0TEST0001', 'status' => 200, 'ms' => 4200, 'cost_usd' => 0.00045, 'created_at' => now()]);
        ProviderHealth::query()->create(['provider' => 'oxylabs', 'state' => 'closed', 'last_success_at' => now()]);

        $order = Order::query()->create([
            'id' => 'SR-TEST01', 'user_id' => $this->customer()->id, 'status' => 'placed', 'payment_method' => 'bkash', 'payment_status' => 'paid',
            'subtotal_bdt' => 3500, 'shipping_bdt' => 400, 'total_bdt' => 3900, 'paid_bdt' => 3900, 'fx_rate' => 1.42, 'tracking_code' => 'SKY12345678',
            'address' => ['name' => 'Rahim', 'phone' => '+8801711000000', 'division' => 'Dhaka', 'district' => 'Dhaka', 'area' => 'Mirpur', 'line' => 'House 1'],
        ]);
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => 'B0TEST0001', 'market' => 'amazon', 'title' => 'Wireless earbuds', 'qty' => 1, 'unit_bdt' => 3500, 'unit_inr' => 1999, 'weight_kg' => 0.2]);
        OrderEvent::query()->create(['order_id' => $order->id, 'status' => 'placed', 'source' => 'customer', 'at' => now()]);
        Payment::query()->create(['order_id' => $order->id, 'gateway' => 'bkash', 'amount_bdt' => 3900, 'status' => 'paid']);

        return $order;
    }

    public function test_admin_can_load_dashboard(): void
    {
        $this->seedData();

        $this->actingAs($this->admin())->get('/admin')
            ->assertOk()
            ->assertSee('Skydrop Retail')
            ->assertSee('Spent this month');
    }

    public function test_non_admin_gets_403(): void
    {
        $this->actingAs($this->customer())->get('/admin')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_every_resource_index_renders(): void
    {
        $order = $this->seedData();
        $this->actingAs($this->admin());

        $resources = [
            OrderResource::class, UserResource::class, PaymentResource::class, CouponResource::class, SupportRequestResource::class,
            ProductReviewResource::class, PointsLedgerResource::class, ProductResource::class, CategoryResource::class,
            MarketplaceResource::class, ProductWatchResource::class, PricingRuleResource::class, FxRateResource::class,
            ApiLogResource::class, ProviderHealthResource::class, StaffResource::class,
        ];
        foreach ($resources as $resource) {
            $this->get($resource::getUrl('index'))->assertOk();
        }

        $this->get(PurchaseQueue::getUrl())->assertOk()->assertSee('Wireless earbuds');
        $this->get(Settings::getUrl())->assertOk()->assertSee('Monthly budget');
    }

    public function test_detail_pages_render(): void
    {
        $order = $this->seedData();
        $this->actingAs($this->admin());

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()->assertSee('SR-TEST01')->assertSee('https://www.amazon.in/dp/B0TEST0001', false);
        $this->get(ProductResource::getUrl('view', ['record' => 'B0TEST0001']))->assertOk()->assertSee('Battery');
        $this->get(UserResource::getUrl('view', ['record' => $order->user_id]))->assertOk()->assertSee('Rahim Uddin');
        $this->get(PricingRuleResource::getUrl('create'))->assertOk()->assertSee('Shopper pays');
        $this->get(route('filament.admin.invoices', ['ids' => $order->id]))->assertOk()->assertSee('Invoice SR-TEST01');
    }

    public function test_support_role_cannot_open_settings_or_staff(): void
    {
        $this->actingAs($this->admin('support'));

        $this->get(Settings::getUrl())->assertForbidden();
        $this->get(StaffResource::getUrl('index'))->assertForbidden();
        $this->get(OrderResource::getUrl('index'))->assertOk();
    }

    public function test_order_workflow_bridge_moves_status_and_records_refund(): void
    {
        $order = $this->seedData();
        $this->actingAs($this->admin());

        $item = $order->items()->first();
        $order = Ops::markPurchased($order->load('items'), [$item->id => '403-1111111-2222222']);
        $this->assertSame('purchased', $order->status);
        $this->assertSame('403-1111111-2222222', $item->refresh()->purchase_ref);

        $order = Ops::handToSkydrop($order);
        $this->assertSame('india_hub', $order->status);
        $this->assertNotNull($order->skydrop_consignment_id);

        $payment = $order->payments()->first();
        Ops::refund($payment, 1000, 'Partial refund test');
        $this->assertEquals(1000, (float) $payment->refresh()->refunded_bdt);
    }

    public function test_settings_are_saved_to_settings_table(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->set('data.oxylabs.monthly_budget_usd', 250)
            ->set('data.variants.mode', 'intent')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(250, Setting::get('oxylabs.monthly_budget_usd'));
        $this->assertSame('intent', Setting::get('variants.mode'));
    }

    public function test_widgets_and_relation_managers_render(): void
    {
        $order = $this->seedData();
        $this->actingAs($this->admin());

        foreach ([
            OrderStatsOverview::class, OxylabsBudget::class,
            ProviderHealthTable::class, ApiCallsChart::class,
            OrdersByStatusChart::class, OrdersByMarketplaceChart::class,
            LowMarginAlerts::class,
        ] as $widget) {
            Livewire::test($widget)->assertOk();
        }

        $user = User::query()->find($order->user_id);
        foreach ([
            OrdersRelationManager::class,
            PointsRelationManager::class,
            ReviewsRelationManager::class,
            SupportRequestsRelationManager::class,
        ] as $rm) {
            Livewire::test($rm, ['ownerRecord' => $user, 'pageClass' => ViewUser::class])->assertOk();
        }
    }

    public function test_order_page_actions_run(): void
    {
        $order = $this->seedData();
        $this->actingAs($this->admin());
        $item = $order->items()->first();

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('verify', ['note' => 'checked'])
            ->assertHasNoActionErrors();
        $this->assertSame('verified', $order->refresh()->status);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('markPurchased', ['refs' => [$item->id => '403-9'], 'note' => null])
            ->assertHasNoActionErrors();
        $this->assertSame('purchased', $order->refresh()->status);

        Livewire::test(ViewUser::class, ['record' => $order->user_id])
            ->callAction('adjustPoints', ['points' => 150, 'note' => 'Goodwill'])
            ->assertHasNoActionErrors();
        $this->assertSame(150, (int) User::query()->find($order->user_id)->points_balance);

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$order->refresh()]);
    }
}
