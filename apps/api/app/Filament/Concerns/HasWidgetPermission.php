<?php

namespace App\Filament\Concerns;

use App\Filament\Support\Admin;
use App\Support\Rbac\PermissionRegistry;

/**
 * Guards a widget with "widget.{kebab-class-name}". Dashboard widgets are already filtered centrally by
 * App\Filament\Pages\Dashboard; use this for widgets shown on other pages (resource header widgets etc.).
 */
trait HasWidgetPermission
{
    public static function canView(): bool
    {
        return Admin::can(PermissionRegistry::widgetKey(static::class));
    }
}
