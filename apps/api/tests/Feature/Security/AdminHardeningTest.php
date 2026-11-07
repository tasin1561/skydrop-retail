<?php

namespace Tests\Feature\Security;

use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Http\Middleware\AdminGuard;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\CsvExport;
use App\Support\Knobs;
use App\Support\Rbac\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Security audit 2026-11: admin-side RBAC holes, Livewire middleware and spreadsheet formula injection. */
class AdminHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $u = new User;
        $u->forceFill(['name' => 'Staff '.uniqid(), 'email' => uniqid().'@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'support'])->save();
        $u->roles()->sync(Role::query()->where('slug', $role)->pluck('id')->all());
        app(PermissionResolver::class)->flush();

        return $u;
    }

    private function as(User $u): static
    {
        $this->flushSession();
        app('auth')->forgetGuards();
        app(PermissionResolver::class)->flush();

        return $this->actingAs($u);
    }

    private function order(string $id, string $status, string $name = 'Rahim'): Order
    {
        $c = new User;
        $c->forceFill(['name' => $name, 'phone' => '+88017'.random_int(10000000, 99999999)])->save();

        return Order::query()->create(['id' => $id, 'user_id' => $c->id, 'status' => $status, 'payment_method' => 'bkash', 'payment_status' => 'paid',
            'subtotal_bdt' => 1000, 'shipping_bdt' => 100, 'total_bdt' => 1100, 'paid_bdt' => 1100, 'fx_rate' => 1.42,
            'address' => ['name' => $name, 'phone' => '+8801711000000', 'division' => 'Dhaka', 'district' => 'Dhaka', 'area' => 'Mirpur', 'line' => 'H1']]);
    }

    public function test_printable_invoices_follow_order_permissions_and_scope(): void
    {
        $placed = $this->order('SR-PLACE1', 'placed');
        $hub = $this->order('SR-ATHUB2', 'india_hub');

        $this->as($this->staff('marketing'))->get(route('filament.admin.invoices', ['ids' => $placed->id]))->assertForbidden();
        $this->as($this->staff('hub_staff'))->get(route('filament.admin.invoices', ['ids' => $placed->id]))->assertNotFound();
        $this->as($this->staff('hub_staff'))->get(route('filament.admin.invoices', ['ids' => $hub->id]))->assertOk();
        $this->as($this->staff('super_admin'))->get(route('filament.admin.invoices', ['ids' => $placed->id]))->assertOk();
    }

    public function test_admin_ip_allowlist_covers_hub_labels_and_livewire_requests(): void
    {
        $this->assertContains(AdminGuard::class, Livewire::getPersistentMiddleware());

        $o = $this->order('SR-LABEL1', 'india_hub');
        $admin = $this->staff('super_admin');
        config(['security.admin.ip_allowlist' => '10.9.9.9']);
        Knobs::forget();
        $this->as($admin)->get(route('hub.labels', ['orders' => $o->id]))->assertForbidden();
        config(['security.admin.ip_allowlist' => '127.0.0.1']);
        Knobs::forget();
        $this->as($admin)->get(route('hub.labels', ['orders' => $o->id]))->assertOk();
    }

    public function test_exports_neutralise_spreadsheet_formulas(): void
    {
        foreach (['=HYPERLINK("http://x","y")', '+1+1', '@SUM(A1)', "\t=1", '-cmd'] as $evil) {
            $this->assertStringStartsWith("'", (string) CsvExport::safe($evil));
        }
        $this->assertSame('-120.5', CsvExport::safe('-120.5'));
        $this->assertSame(42, CsvExport::safe(42));

        $o = $this->order('SR-CSV001', 'placed', '=cmd|\' /C calc\'!A0');
        ob_start();
        OrderActions::csv(collect([$o]))->sendContent();
        $csv = (string) ob_get_clean();
        $this->assertStringContainsString("'=cmd", $csv);
        $this->assertStringNotContainsString(',=cmd', $csv);
    }
}
