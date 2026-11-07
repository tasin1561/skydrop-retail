<?php

namespace Tests\Feature\Admin;

use App\Jobs\Commerce\CreateSkydropConsignment;
use App\Models\Fulfilment\ReturnRequest;
use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\Rbac\PermissionResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route as RouteFacade;
use Livewire\Livewire;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;
use Throwable;

/**
 * Renders every Filament resource list / create page and every custom page as a super admin and expects 200.
 * Routes are enumerated from the "admin" panel, so new resources and pages are covered automatically.
 * Admin 2FA is off under test (config/security.php: admin.require_2fa defaults to false when APP_ENV=testing).
 */
class AdminSmokeTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    /** Panel routes that are not admin screens (auth flow, file downloads needing query params). */
    private const SKIP = ['filament.admin.auth.login', 'filament.admin.auth.logout', 'filament.admin.invoices'];

    /** @return list<string> route names */
    private function panelRoutes(): array
    {
        $names = [];
        /** @var Route $route */
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'filament.admin.') || in_array($name, self::SKIP, true) || ! in_array('GET', $route->methods(), true)
                || str_starts_with($name, 'filament.admin.auth.multi-factor') || $route->parameterNames() !== []) {
                continue;
            }
            $names[] = $name;
        }
        sort($names);

        return $names;
    }

    private function seedSomeData(): void
    {
        $this->setUpCommerce();
        Bus::fake([CreateSkydropConsignment::class]);
        $u = $this->shopper();
        $order = $this->placeOrder($u, [$this->product('SMOKE01', 1200), $this->product('SMOKE02', 800, 'flipkart', 'fashion')]);
        SupportRequest::query()->create(['order_id' => $order->id, 'user_id' => $u->id, 'type' => 'other', 'message' => 'Smoke test', 'status' => 'open', 'priority' => true]);
        ReturnRequest::query()->create(['order_id' => $order->id, 'user_id' => $u->id, 'stage' => 'delivered', 'items' => [['orderItemId' => $order->items->first()->id, 'qty' => 1]],
            'reason' => 'size_issue', 'resolution' => 'refund', 'status' => 'requested', 'refund_bdt' => 900, 'return_fee_bdt' => 100, 'timeline' => []]);
    }

    private function superAdmin(): User
    {
        $user = new User;
        $user->forceFill(['name' => 'Smoke Admin', 'email' => 'smoke@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'super_admin')->pluck('id')->all());
        app(PermissionResolver::class)->flush();

        return $user;
    }

    public function test_every_admin_page_renders_for_a_super_admin(): void
    {
        $this->assertFalse((bool) config('security.admin.require_2fa'), '2FA must be off under test');
        $this->seedSomeData();
        $this->flushSession();
        app('auth')->forgetGuards();
        $this->actingAs($this->superAdmin(), 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $routes = $this->panelRoutes();
        $this->assertGreaterThan(50, count($routes), 'panel routes were not discovered');

        $failed = [];
        foreach ($routes as $name) {
            try {
                $res = $this->get(route($name));
                if ($res->getStatusCode() !== 200) {
                    $failed[$name] = $res->getStatusCode().' '.mb_substr(strip_tags((string) ($res->exception?->getMessage() ?? '')), 0, 300);
                }
            } catch (Throwable $e) {
                $failed[$name] = get_class($e).': '.$e->getMessage();
            }
        }

        // Record pages (view / edit) for every resource that has at least one row in the seeded data.
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $record = rescue(fn () => $resource::getEloquentQuery()->first(), null, false);
            if (! $record) {
                continue;
            }
            foreach (['view', 'edit'] as $page) {
                if (! $resource::hasPage($page) || ! $resource::canAccess()) {
                    continue;
                }
                try {
                    $res = $this->get($resource::getUrl($page, ['record' => $record]));
                    if ($res->getStatusCode() !== 200) {
                        $failed[$resource.'@'.$page] = $res->getStatusCode().' '.mb_substr((string) ($res->exception?->getMessage() ?? ''), 0, 300);
                    }
                } catch (Throwable $e) {
                    $failed[$resource.'@'.$page] = get_class($e).': '.$e->getMessage();
                }
            }
        }

        // Dashboard widgets (lazy ones are not rendered by the page request).
        foreach (Filament::getPanel('admin')->getWidgets() as $widget) {
            $class = is_string($widget) ? $widget : $widget->widget;
            if (! $class::canView()) { // e.g. AI assistant usage while the assistant feature is off
                continue;
            }
            try {
                Livewire::test($class)->assertOk();
            } catch (Throwable $e) {
                $failed[$class] = get_class($e).': '.mb_substr($e->getMessage(), 0, 300);
            }
        }

        $this->assertSame([], $failed, 'Admin pages that did not render: '.json_encode($failed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
