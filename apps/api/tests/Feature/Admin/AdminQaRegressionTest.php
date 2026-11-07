<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\PurchaseQueue;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
