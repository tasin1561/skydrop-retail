<?php

namespace App\Filament\Concerns;

use App\Filament\Support\Admin;
use App\Support\Rbac\PermissionRegistry;

/**
 * Guards a custom Filament page with "page.{slug}" (hides it from navigation, 403 on open). Optionally require more
 * keys:  protected static array $requiredPermissions = ['settings.manage'];
 *
 * Every page under App\Filament\Pages should use this. The AuthorizePanelPage middleware also enforces "page.{slug}"
 * on page routes as a backstop, but only this trait hides the navigation item.
 */
trait HasPagePermission
{
    public static function canAccess(): bool
    {
        if (! Admin::can(PermissionRegistry::pageKey(static::class))) {
            return false;
        }
        foreach (static::requiredPermissions() as $key) {
            if (! Admin::can($key)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public static function requiredPermissions(): array
    {
        return property_exists(static::class, 'requiredPermissions') ? (array) static::$requiredPermissions : [];
    }
}
