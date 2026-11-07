<?php

namespace Tests\Feature\Fulfilment;

use App\Filament\Pages\Hub\FulfilmentSettingsPage;
use App\Filament\Pages\Hub\HubConsolidation;
use App\Filament\Pages\Hub\HubReceive;
use App\Filament\Pages\PriceAlerts;
use App\Filament\Pages\PurchaseQueue;
use App\Filament\Resources\HubTasks\HubTaskResource;
use App\Filament\Resources\HubTasks\Pages\ListHubTasks;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\ReturnRequests\Pages\ViewReturnRequest;
use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Filament\Widgets\HubStatsOverview;
use App\Jobs\Commerce\CreateSkydropConsignment;
use App\Models\Fulfilment\HubBox;
use App\Models\Fulfilment\HubTask;
use App\Models\Fulfilment\ReturnRequest;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Fulfilment\FulfilmentSettings;
use App\Services\Fulfilment\HubService;
use App\Services\Fulfilment\PriceAlertService;
use Database\Seeders\AdminUserSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class FulfilmentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([CreateSkydropConsignment::class]);
        config(['retail.skydrop.mode' => 'mock', 'retail.sms.driver' => 'log', 'retail.payments.mode' => 'mock']);
        $this->seed(AdminUserSeeder::class);
        $admin = User::query()->where('email', 'admin@skydrop.retail')->first()
            ?? User::query()->forceCreate(['name' => 'Admin', 'email' => 'admin@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function order(string $status = 'purchased', bool $unboxing = false): Order
    {
        $u = User::query()->forceCreate(['name' => 'Rahim', 'phone' => '+88017'.random_int(10000000, 99999999)]);
        Product::query()->firstOrCreate(['id' => 'B0ADMIN001'], ['market' => 'amazon', 'category' => 'electronics', 'title' => 'Earbuds', 'price_inr' => 1000, 'mrp_inr' => 1200, 'in_stock' => true]);
        $o = Order::query()->create([
            'id' => 'SR-'.strtoupper(substr(md5((string) microtime(true)), 0, 6)), 'user_id' => $u->id, 'status' => $status, 'payment_method' => 'bkash',
            'payment_status' => 'paid', 'subtotal_bdt' => 2000, 'shipping_bdt' => 300, 'total_bdt' => 2300, 'paid_bdt' => 2300, 'fx_rate' => 1.42,
            'tracking_code' => 'SD'.random_int(100000000, 999999999), 'unboxing' => $unboxing,
            'address' => ['name' => 'Rahim', 'phone' => '+8801711000000', 'division' => 'Dhaka', 'district' => 'Gazipur', 'area' => 'Tongi', 'line' => 'House 1'],
        ]);
        OrderItem::query()->create(['order_id' => $o->id, 'product_id' => 'B0ADMIN001', 'market' => 'amazon', 'title' => 'Earbuds', 'qty' => 1,
            'unit_bdt' => 2000, 'unit_inr' => 1000, 'weight_kg' => 0.3, 'purchase_ref' => '403-'.$o->id, 'fx_rate' => 1.42]);
        OrderEvent::query()->create(['order_id' => $o->id, 'status' => 'purchased', 'source' => 'admin', 'at' => now()->subDays(2)]);

        return $o;
    }

    public function test_receive_page_scans_and_receives_an_item(): void
    {
        $o = $this->order();
        $this->get(HubReceive::getUrl())->assertOk()->assertSee('Scan or type');

        Livewire::test(HubReceive::class)
            ->set('code', '403-'.$o->id)->call('lookup')
            ->assertSet('itemId', $o->items()->first()->id)
            ->fillForm(['weight_kg' => 0.35, 'condition' => 'ok', 'length_cm' => 20, 'width_cm' => 12, 'height_cm' => 6])
            ->call('receive')
            ->assertHasNoErrors()
            ->assertSet('itemId', null);

        $this->assertSame('india_hub', $o->refresh()->status);
        $this->assertDatabaseHas('hub_receipts', ['order_id' => $o->id, 'condition' => 'ok']);
    }

    public function test_consolidation_pack_and_label_and_widget(): void
    {
        $o = $this->order();
        app(HubService::class)->receive($o->items()->first(), ['weight_kg' => 0.5]);
        $this->get(HubConsolidation::getUrl())->assertOk()->assertSee($o->id);
        Livewire::test(HubConsolidation::class)->callTableAction('pack', $o->refresh())->assertHasNoTableActionErrors();
        $this->assertSame(1, HubBox::query()->where('order_id', $o->id)->count());

        $this->get(route('hub.labels', ['orders' => $o->id]))->assertOk()->assertSee($o->id)->assertSee('<svg', false)->assertSee('Gazipur');
        Livewire::test(HubStatsOverview::class)->assertOk()->assertSee('Purchase → hub');
        $this->get(ViewOrder::getUrl(['record' => $o]))->assertOk()->assertSee('Received at the Kolkata hub');
    }

    public function test_price_alerts_page_banner_and_absorb(): void
    {
        $o = $this->order('verified');
        $item = $o->items()->first();
        $item->forceFill(['purchase_ref' => null])->save();
        Product::query()->whereKey('B0ADMIN001')->update(['price_inr' => 1300]);
        app(PriceAlertService::class)->check($item);

        $this->get(PurchaseQueue::getUrl())->assertOk()->assertSee('price alert(s)');
        $this->get(PriceAlerts::getUrl())->assertOk()->assertSee('+30%');
        Livewire::test(PriceAlerts::class)->callTableAction('absorb', $item->refresh())->assertHasNoTableActionErrors();
        $this->assertSame('absorb', $item->refresh()->price_alert['resolution']);
        $this->assertFalse($o->refresh()->price_alert);
    }

    public function test_returns_resource_and_actions(): void
    {
        $o = $this->order('delivered');
        $o->forceFill(['delivered_at' => now()])->save();
        $r = ReturnRequest::query()->create(['order_id' => $o->id, 'user_id' => $o->user_id, 'items' => [['orderItemId' => $o->items()->first()->id, 'qty' => 1]],
            'reason' => 'damaged', 'resolution' => 'refund', 'status' => 'requested', 'refund_bdt' => 2000,
            'timeline' => [['status' => 'requested', 'at' => now()->toIso8601String(), 'note' => null, 'by' => 'customer']]]);

        $this->get(ReturnRequestResource::getUrl())->assertOk()->assertSee($o->id);
        $this->get(ReturnRequestResource::getUrl('view', ['record' => $r]))->assertOk()->assertSee('Return #'.$r->id);
        Livewire::test(ViewReturnRequest::class, ['record' => $r->id])->callAction('approve', ['note' => 'OK'])->assertHasNoActionErrors();
        $this->assertSame('approved', $r->refresh()->status);
        Livewire::test(ListReturnRequests::class)->assertCanSeeTableRecords([$r]);
    }

    public function test_hub_tasks_and_settings_pages(): void
    {
        $o = $this->order();
        app(HubService::class)->receive($o->items()->first(), ['weight_kg' => 0.5, 'condition' => 'wrong_item']);
        $this->get(HubTaskResource::getUrl())->assertOk()->assertSee('Return to seller');
        Livewire::test(ListHubTasks::class)->callTableAction('done', HubTask::query()->first(), ['note' => 'RMA 123']);
        $this->assertSame('done', HubTask::query()->first()->status);

        $this->get(FulfilmentSettingsPage::getUrl())->assertOk();
        Livewire::test(FulfilmentSettingsPage::class)->fillForm(['unboxing' => ['fee_bdt' => 200, 'per' => 'item', 'auto_approve_hours' => 24]])->call('save');
        $this->assertEquals(200, FulfilmentSettings::get('unboxing.fee_bdt'));
        $this->assertSame('item', FulfilmentSettings::get('unboxing.per'));
    }

    public function test_labels_require_staff(): void
    {
        auth()->logout();
        $this->get(route('hub.labels', ['orders' => 'SR-XXXXXX']))->assertRedirect('/admin/login');
    }
}
