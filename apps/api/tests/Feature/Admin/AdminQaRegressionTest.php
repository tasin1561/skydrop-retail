<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\PurchaseQueue;
use App\Filament\Pages\SystemLimits;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Filament\Support\Ops;
use App\Models\Coupon;
use App\Models\Fulfilment\ReturnRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\AccountingService;
use App\Services\Fulfilment\HubService;
use App\Support\AdminSecurity;
use App\Support\BizTime;
use App\Support\Rbac\PermissionResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Regressions found by browser QA of the admin panel (permission holes and staff-facing bugs). */
class AdminQaRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $user = new User;
        $user->forceFill(['name' => 'QA '.$role, 'email' => uniqid().'@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'support'])->save();
        $user->syncRoles(Role::query()->where('slug', $role)->pluck('id')->all());

        return $user;
    }

    private function as(User $user): static
    {
        $this->flushSession();
        app('auth')->forgetGuards();
        app(PermissionResolver::class)->flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $this->actingAs($user);
    }

    private function order(string $id, string $status): Order
    {
        $customer = new User;
        $customer->forceFill(['name' => 'Customer', 'phone' => '+88017'.random_int(10000000, 99999999), 'is_admin' => false])->save();
        $order = Order::query()->create([
            'id' => $id, 'user_id' => $customer->id, 'status' => $status, 'payment_method' => 'bkash', 'payment_status' => 'paid',
            'subtotal_bdt' => 1000, 'shipping_bdt' => 100, 'total_bdt' => 1100, 'paid_bdt' => 1100, 'fx_rate' => 1.42,
            'address' => ['name' => 'R', 'phone' => '+8801711000000', 'division' => 'Dhaka', 'district' => 'Dhaka', 'area' => 'Mirpur', 'line' => 'H1'],
        ]);
        OrderItem::query()->create(['order_id' => $id, 'product_id' => 'B0TEST', 'market' => 'amazon', 'title' => 'Item', 'qty' => 1, 'unit_bdt' => 1000, 'unit_inr' => 700, 'weight_kg' => 0.2]);

        return $order;
    }

    public function test_every_staff_member_can_open_their_own_profile(): void
    {
        foreach (['support', 'hub_staff', 'catalog'] as $role) {
            $this->as($this->staff($role))->get('/admin/profile')->assertOk();
        }
    }

    public function test_view_only_staff_cannot_run_order_workflow_actions(): void
    {
        $placed = $this->order('SR-QAP1', 'placed');
        $hub = $this->order('SR-QAH1', 'india_hub');

        $this->as($this->staff('support'));
        Livewire::test(ViewOrder::class, ['record' => $placed->id])->assertActionHidden('verify')->assertActionHidden('notes');
        Livewire::test(ViewOrder::class, ['record' => $hub->id])->assertActionHidden('advance');
        Livewire::test(ListOrders::class)->assertActionHidden('exportAll')
            ->assertTableBulkActionHidden('bulkVerify')->assertTableBulkActionHidden('bulkPurchased');
        Livewire::test(PurchaseQueue::class)->assertTableActionHidden('bought', $placed->items()->first());

        $this->as($this->staff('hub_staff'));
        Livewire::test(ViewOrder::class, ['record' => $hub->id])->assertActionHidden('advance')->assertActionVisible('handToSkydrop');
        Livewire::test(ListOrders::class)->assertActionHidden('queue');

        $this->as($this->staff('order_ops'));
        Livewire::test(ViewOrder::class, ['record' => $placed->id])->assertActionVisible('verify');
        Livewire::test(PurchaseQueue::class)->assertTableActionVisible('bought', $placed->items()->first());
    }

    public function test_return_view_shows_status_reason_and_resolution_labels(): void
    {
        $this->as($this->staff('manager'));
        $o = $this->order('SR-QAR1', 'delivered');
        $r = ReturnRequest::query()->create(['order_id' => $o->id, 'user_id' => $o->user_id, 'items' => [['orderItemId' => $o->items()->first()->id, 'qty' => 1]],
            'reason' => 'wrong_item', 'resolution' => 'store_credit', 'status' => 'pickup_scheduled', 'refund_bdt' => 1000,
            'timeline' => [['status' => 'requested', 'at' => now()->toIso8601String(), 'note' => null]]]);

        // The infolist closures used "$s", which Filament cannot inject, so these all rendered blank.
        $this->get(ReturnRequestResource::getUrl('view', ['record' => $r]))->assertOk()
            ->assertSee('Pickup scheduled')->assertSee('Wrong item')->assertSee('Store credit')->assertSee('৳1,000');
        $this->get(ReturnRequestResource::getUrl())->assertOk()->assertSee('Pickup scheduled')->assertSee('Wrong item');
    }

    public function test_hub_item_cannot_be_received_twice_and_hand_to_skydrop_moves_hub_orders_on(): void
    {
        $this->as($this->staff('hub_staff'));
        $o = $this->order('SR-QAH2', 'purchased');
        $item = $o->items()->first();
        app(HubService::class)->receive($item, ['weight_kg' => 0.2, 'condition' => 'ok']);
        $this->assertSame('india_hub', $o->refresh()->status);

        try {
            app(HubService::class)->receive($item->refresh(), ['weight_kg' => 0.2, 'condition' => 'ok']);
            $this->fail('A second receipt was accepted.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('already received', $e->getMessage());
        }

        $o = Ops::handToSkydrop($o->refresh());
        $this->assertSame('in_transit', $o->status);
    }

    public function test_coupon_form_rejects_bad_codes_and_values(): void
    {
        $this->as($this->staff('marketing'));
        Coupon::query()->create(['code' => 'SKYDROP10', 'type' => 'percent_items', 'value' => 10, 'active' => true]);

        Livewire::test(CreateCoupon::class)->fillForm(['code' => 'skydrop10', 'type' => 'percent_items', 'value' => 5])->call('create')->assertHasFormErrors(['code']);
        Livewire::test(CreateCoupon::class)->fillForm(['code' => 'QA BIG', 'type' => 'percent_items', 'value' => 5])->call('create')->assertHasFormErrors(['code']);
        Livewire::test(CreateCoupon::class)->fillForm(['code' => 'QA150', 'type' => 'percent_items', 'value' => 150])->call('create')->assertHasFormErrors(['value']);
        Livewire::test(CreateCoupon::class)->fillForm(['code' => 'QAFIX', 'type' => 'fixed', 'value' => null])->call('create')->assertHasFormErrors(['value']);
        Livewire::test(CreateCoupon::class)->fillForm(['code' => 'qa20', 'type' => 'percent_items', 'value' => 20])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('coupons', ['code' => 'QA20', 'value' => 20]);
    }

    public function test_manager_cannot_change_the_admin_session_timeout_on_the_limits_page(): void
    {
        $this->as($this->staff('manager'));
        $this->assertFalse(SystemLimits::editable('api.admin_session_minutes'));
        $this->get(SystemLimits::getUrl())->assertOk()->assertDontSee('Admin idle session timeout');

        $this->as($this->staff('super_admin'));
        $this->assertTrue(SystemLimits::editable('api.admin_session_minutes'));
    }

    public function test_ip_allowlist_accepts_one_entry_per_line(): void
    {
        $this->assertSame(['10.0.0.1', '103.10.20.0/24', '127.0.0.1'], AdminSecurity::allowlist("10.0.0.1\n103.10.20.0/24,  127.0.0.1\n"));
    }

    public function test_accounting_groups_orders_by_dhaka_day(): void
    {
        $o = $this->order('SR-QAT1', 'verified');
        // 01:30 on 5 Nov in Dhaka is 19:30 UTC on 4 Nov.
        $o->forceFill(['created_at' => Carbon::parse('2026-11-04 19:30:00', 'UTC')])->save();

        $this->assertSame('2026-11-05', BizTime::date($o->created_at));
        [, $rows] = app(AccountingService::class)->salesJournal(Carbon::parse('2026-11-05'), Carbon::parse('2026-11-05'));
        $this->assertSame('2026-11-05', $rows[0][0] ?? null);
    }

    public function test_activity_log_shows_readable_action_names(): void
    {
        $this->assertSame('Set Up App Authentication', ActivityLogResource::actionLabel('action:setUpAppAuthentication'));
        $this->assertSame('Roles Permissions Changed', ActivityLogResource::actionLabel('roles.permissions_changed'));
    }
}
