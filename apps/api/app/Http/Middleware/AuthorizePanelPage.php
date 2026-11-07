<?php

namespace App\Http\Middleware;

use App\Filament\Support\Admin;
use App\Support\Rbac\PermissionRegistry;
use Closure;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backstop for custom admin pages: opening a page route requires "page.{slug}", even when the page class forgot the
 * HasPagePermission trait. Resource pages (covered by the Gate) and the Dashboard are skipped.
 */
class AuthorizePanelPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $class = $request->route()?->getControllerClass();
        if ($class && is_subclass_of($class, Page::class) && ! is_subclass_of($class, ResourcePage::class)
            && ! is_a($class, Dashboard::class, true) && ! self::isAccountPage($class) && $request->user()) {
            abort_unless(Admin::can(PermissionRegistry::pageKey($class), $request->user()), 403);
        }

        return $next($request);
    }

    /** Every staff member's own account pages (profile: password + optional 2FA, required-2FA set-up) are always open. */
    private static function isAccountPage(string $class): bool
    {
        return str_starts_with($class, 'Filament\\Auth\\');
    }
}
