<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\FeatureSwitches;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Filament\Support\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionPreview;
use App\Support\Rbac\PermissionRegistry;
use App\Support\Rbac\PermissionResolver;
use App\Support\Rbac\RoleGuard;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string|array $roles, array $attrs = []): User
    {
        $user = new User;
        $user->forceFill($attrs + ['name' => 'Staff '.uniqid(), 'email' => uniqid().'@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'support'])->save();
        $ids = Role::query()->whereIn('slug', (array) $roles)->pluck('id')->all();
        if ($ids) {
            $user->roles()->sync($ids);
        }
        app(PermissionResolver::class)->flush();

        return $user;
    }

    /** Switch the signed-in staff member with a fresh session (AuthenticateSession would log a switched user out). */
    private function as(User $user): static
    {
        $this->flushSession();
        app('auth')->forgetGuards();
        app(PermissionResolver::class)->flush();

        return $this->actingAs($user);
    }

    private function legacy(?string $role): User
    {
        $user = new User;
        $user->forceFill(['name' => 'Legacy '.$role, 'email' => uniqid().'@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => $role])->save();

        return $user;
    }

    private function order(string $id, string $status): Order
    {
        $customer = new User;
        $customer->forceFill(['name' => 'Customer', 'phone' => '+88017'.random_int(10000000, 99999999), 'is_admin' => false])->save();

        return Order::query()->create([
            'id' => $id, 'user_id' => $customer->id, 'status' => $status, 'payment_method' => 'bkash', 'payment_status' => 'paid',
            'subtotal_bdt' => 1000, 'shipping_bdt' => 100, 'total_bdt' => 1100, 'paid_bdt' => 1100, 'fx_rate' => 1.42,
            'address' => ['name' => 'R', 'phone' => '+8801711000000', 'division' => 'Dhaka', 'district' => 'Dhaka', 'area' => 'Mirpur', 'line' => 'H1'],
        ]);
    }

    public function test_migration_seeds_default_roles(): void
    {
        $this->assertSame(['super_admin', 'manager', 'order_ops', 'support', 'hub_staff', 'finance', 'marketing', 'catalog'], Role::query()->orderBy('id')->pluck('slug')->all());
        $this->assertSame(['*'], Role::query()->where('slug', 'super_admin')->value('permissions'));
    }

    public function test_resource_list_page_is_allowed_or_denied_by_permission(): void
    {
        $this->as($this->staff('order_ops'));
        $this->get(OrderResource::getUrl('index'))->assertOk();
        $this->get(CouponResource::getUrl('index'))->assertForbidden();
        $this->get(RoleResource::getUrl('index'))->assertForbidden();

        $this->as($this->staff('marketing'));
        $this->get(CouponResource::getUrl('index'))->assertOk();
        $this->get(OrderResource::getUrl('index'))->assertForbidden();
    }

    public function test_custom_role_grants_exact_resource_abilities(): void
    {
        $role = Role::query()->create(['name' => 'Coupon viewer', 'slug' => 'coupon_viewer', 'permissions' => ['coupons.viewAny']]);
        $user = $this->staff([]);
        $user->roles()->sync([$role->id]);
        $this->as($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(CouponResource::getUrl('index'))->assertOk();
        $this->assertTrue(CouponResource::canViewAny());
        $this->assertFalse(CouponResource::canCreate());
        $this->get(CouponResource::getUrl('create'))->assertForbidden();
    }

    public function test_custom_action_refund_needs_orders_refund(): void
    {
        $order = $this->order('SR-RBAC01', 'verified');
        Payment::query()->create(['order_id' => $order->id, 'gateway' => 'bkash', 'amount_bdt' => 1100, 'status' => 'paid']);

        $this->as($this->staff('order_ops'));
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionHidden('refund')->assertActionVisible('cancel');

        $this->as($this->staff('finance'));
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionVisible('refund')->assertActionHidden('cancel');
    }

    public function test_legacy_role_string_still_works(): void
    {
        $manager = $this->legacy('manager');
        $this->assertTrue(Admin::can('orders.refund', $manager));
        $this->assertTrue(Admin::isManager($manager));
        $this->assertTrue(Admin::canManage($manager));
        $this->assertFalse(Admin::can('staff.viewAny', $manager));
        $this->assertFalse(Admin::isAdmin($manager));

        $support = $this->legacy('support');
        $this->assertTrue(Admin::can('customers.view', $support));
        $this->assertFalse(Admin::can('orders.refund', $support));

        $admin = $this->legacy(null);
        $this->assertTrue(Admin::isAdmin($admin));
        $this->assertTrue(Admin::can('anything.new', $admin));

        $this->as($support)->get(Settings::getUrl())->assertForbidden();
        $this->as($support)->get(StaffResource::getUrl('index'))->assertForbidden();
    }

    public function test_wildcard_denials_and_restriction_keys(): void
    {
        $manager = $this->staff('manager');
        $this->assertTrue(Admin::can('some-future-resource.delete', $manager));
        $this->assertFalse(Admin::can('staff.viewAny', $manager));
        $this->assertFalse(Admin::can('features.manage', $manager));
        $this->assertFalse(Admin::can('orders.scope.hub_only', $manager)); // wildcards never grant restrictions
        $this->assertFalse(Admin::can('page.finance-security-settings', $manager));

        $both = $this->staff(['support', 'manager']);
        $this->assertTrue(Admin::can('orders.refund', $both)); // a denial only affects its own role
    }

    public function test_last_super_admin_cannot_be_demoted_or_deleted(): void
    {
        $boss = $this->staff('super_admin');
        $managerRole = Role::query()->where('slug', 'manager')->value('id');

        try {
            $boss->syncRoles([$managerRole]);
            $this->fail('Demoting the last super admin should fail');
        } catch (RuntimeException) {
            $this->assertTrue($boss->refresh()->isSuperAdmin());
        }

        $this->expectException(RuntimeException::class);
        Role::query()->where('slug', 'super_admin')->first()->delete();
    }

    public function test_super_admin_can_be_demoted_when_another_exists(): void
    {
        $a = $this->staff('super_admin');
        $this->staff('super_admin');
        $a->syncRoles([Role::query()->where('slug', 'manager')->value('id')]);
        app(PermissionResolver::class)->flush();

        $this->assertFalse($a->refresh()->isSuperAdmin());
        $this->assertSame('manager', $a->role);
        $this->assertFalse(RoleGuard::isLastSuperAdmin($a));
    }

    public function test_blocked_staff_and_customers_cannot_open_panel(): void
    {
        $blocked = $this->staff('super_admin', ['blocked_at' => now()]);
        $this->as($blocked)->get('/admin')->assertForbidden();

        $roleOnly = $this->staff('support', ['is_admin' => false]);
        $this->as($roleOnly)->get('/admin')->assertOk();
    }

    public function test_hub_staff_only_see_hub_stage_orders(): void
    {
        $placed = $this->order('SR-PLACED', 'placed');
        $hub = $this->order('SR-ATHUB1', 'india_hub');
        $bought = $this->order('SR-BOUGHT', 'purchased');

        $this->as($this->staff('hub_staff'));
        Livewire::test(ListOrders::class)->set('activeTab', 'all')->assertCanSeeTableRecords([$hub, $bought])->assertCanNotSeeTableRecords([$placed]);
        $this->get(OrderResource::getUrl('view', ['record' => $placed]))->assertNotFound();

        $this->as($this->staff('super_admin'));
        Livewire::test(ListOrders::class)->set('activeTab', 'all')->assertCanSeeTableRecords([$placed, $hub, $bought]);
    }

    public function test_pages_are_guarded_by_page_permissions(): void
    {
        $this->as($this->staff('manager'));
        $this->get(Settings::getUrl())->assertOk();
        $this->get(FeatureSwitches::getUrl())->assertForbidden();

        $this->as($this->staff('super_admin'));
        $this->get(FeatureSwitches::getUrl())->assertOk()->assertSee('Checkout');
    }

    public function test_registry_discovers_panel_resources_pages_widgets_and_extensions(): void
    {
        PermissionRegistry::extend(['wallet.adjust' => 'Adjust wallet'], 'Wallet');
        $all = PermissionRegistry::all();

        foreach (['orders.viewAny', 'orders.export', 'coupons.delete', 'roles.update', 'staff.create', 'customers.view',
            'orders.refund', 'page.features', 'page.purchase-queue', 'widget.order-stats-overview', 'wallet.adjust'] as $key) {
            $this->assertArrayHasKey($key, $all, $key);
        }
    }

    public function test_roles_and_staff_admin_pages_render(): void
    {
        $boss = $this->staff('super_admin');
        $this->as($boss);
        $support = Role::query()->where('slug', 'support')->first();

        $this->get(RoleResource::getUrl('index'))->assertOk()->assertSee('Order Ops');
        $this->get(RoleResource::getUrl('edit', ['record' => $support]))->assertOk()->assertSee('customers.message');
        $this->get(StaffResource::getUrl('index'))->assertOk()->assertSee('Super Admin');
        $this->get(StaffResource::getUrl('edit', ['record' => $boss]))->assertOk();

        Livewire::test(EditRole::class, ['record' => $support->id])
            ->set('data.permissions_exact', ['orders.viewAny', 'orders.view'])
            ->set('data.permissions_rules', ['support*'])
            ->call('save')->assertHasNoErrors();
        $this->assertEqualsCanonicalizing(['orders.viewAny', 'orders.view', 'support*'], $support->refresh()->permissions);
        $this->assertDatabaseHas('activity_log', ['action' => 'roles.permissions_changed', 'subject_id' => (string) $support->id]);

        Livewire::test(ListRoles::class)
            ->callTableAction('clone', $support, ['name' => 'Support night shift']);
        $this->assertDatabaseHas('roles', ['slug' => 'support_night_shift', 'is_system' => false]);
    }

    public function test_staff_edit_assigns_multiple_roles_and_preview_explains(): void
    {
        $this->as($this->staff('super_admin'));
        $agent = $this->staff('support');
        $ids = Role::query()->whereIn('slug', ['support', 'finance'])->pluck('id')->map(fn ($i) => (string) $i)->all();

        Livewire::test(EditStaff::class, ['record' => $agent->id])
            ->set('data.role_ids', $ids)->call('save')->assertHasNoErrors();
        app(PermissionResolver::class)->flush();

        $this->assertEqualsCanonicalizing(['support', 'finance'], $agent->roles()->pluck('slug')->all());
        $this->assertDatabaseHas('activity_log', ['action' => 'staff.roles_changed', 'subject_id' => (string) $agent->id]);
        $preview = PermissionPreview::data($agent->refresh());
        $this->assertSame(['Finance'], $preview['granted']['Actions: Finance']['payments.refund']);
        $this->assertContains('Support', $preview['granted']['Actions: Customers']['customers.message']);
    }
}
