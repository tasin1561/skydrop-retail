<?php

namespace Tests\Feature\Admin;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasWidgetPermission;
use App\Filament\Pages\Broadcast;
use App\Filament\Pages\GrowthSettings;
use App\Filament\Pages\MessagingSettings;
use App\Filament\Pages\PurchaseQueue;
use App\Filament\Pages\Settings;
use App\Filament\Widgets\AssistantUsage;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionRegistry;
use App\Support\Rbac\PermissionResolver;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/** Every custom page and every off-dashboard widget is guarded, so menus never show what a role cannot open. */
class RbacCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<class-string> custom (non-resource) pages registered on the admin panel */
    private function customPages(): array
    {
        return array_values(array_filter(Filament::getPanel('admin')->getPages(), fn ($p) => ! is_subclass_of($p, ResourcePage::class)));
    }

    public function test_every_custom_page_uses_has_page_permission_or_is_the_dashboard(): void
    {
        $pages = $this->customPages();
        $this->assertGreaterThan(10, count($pages));
        $all = PermissionRegistry::all();
        foreach ($pages as $page) {
            if (is_a($page, Dashboard::class, true)) {
                continue;
            }
            $this->assertContains(HasPagePermission::class, class_uses_recursive($page), "{$page} must use HasPagePermission");
            $key = PermissionRegistry::pageKey($page);
            $this->assertArrayHasKey($key, $all, "{$key} is missing from the Roles matrix");
        }
    }

    public function test_widgets_shown_outside_the_dashboard_use_has_widget_permission(): void
    {
        $panel = Filament::getPanel('admin');
        $dashboard = array_map(fn ($w) => is_string($w) ? $w : $w->widget, $panel->getWidgets());
        $pageClasses = $this->customPages();
        foreach ($panel->getResources() as $resource) {
            foreach ($resource::getPages() as $registration) {
                $pageClasses[] = $registration->getPage();
            }
        }

        $offDashboard = [];
        foreach (array_unique($pageClasses) as $page) {
            if (is_a($page, Dashboard::class, true)) {
                continue;
            }
            foreach (['getHeaderWidgets', 'getFooterWidgets'] as $method) {
                if (! method_exists($page, $method)) {
                    continue;
                }
                $m = new ReflectionMethod($page, $method);
                $widgets = rescue(fn () => $m->invoke((new \ReflectionClass($page))->newInstanceWithoutConstructor()), [], false);
                foreach ((array) $widgets as $w) {
                    $offDashboard[] = is_string($w) ? $w : $w->widget;
                }
            }
        }
        // Widget classes that exist but are not discovered for the dashboard are off-dashboard too.
        foreach (glob(app_path('Filament/**/Widgets/*.php')) ?: [] as $file) {
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(app_path().'/')));
            if (class_exists($class) && ! in_array($class, $dashboard, true)) {
                $offDashboard[] = $class;
            }
        }

        $this->assertContains(AssistantUsage::class, $offDashboard); // header widget on Assistant → Conversations
        foreach (array_unique($offDashboard) as $widget) {
            $this->assertContains(HasWidgetPermission::class, class_uses_recursive($widget), "{$widget} must use HasWidgetPermission");
            $this->assertArrayHasKey(PermissionRegistry::widgetKey($widget), PermissionRegistry::all());
        }
    }

    public function test_navigation_hides_pages_a_role_cannot_open(): void
    {
        $user = new User;
        $user->forceFill(['name' => 'Ops', 'email' => 'ops@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'support'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'order_ops')->pluck('id')->all());
        app(PermissionResolver::class)->flush();
        $this->actingAs($user, 'web');

        $this->assertTrue(PurchaseQueue::canAccess());
        foreach ([Broadcast::class, Settings::class, MessagingSettings::class, GrowthSettings::class] as $page) {
            $this->assertFalse($page::canAccess(), "{$page} should be hidden for Order Ops");
            $this->get($page::getUrl())->assertForbidden();
        }
    }
}
