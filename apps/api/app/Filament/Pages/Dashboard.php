<?php

namespace App\Filament\Pages;

use App\Filament\Support\Admin;
use App\Support\Rbac\PermissionRegistry;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\WidgetConfiguration;

/**
 * The panel home. Always reachable by staff; each widget shows only with permission "widget.{kebab-class-name}",
 * so widgets other modules add are covered automatically.
 */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return array_values(array_filter(parent::getWidgets(), function ($widget) {
            $class = $widget instanceof WidgetConfiguration ? $widget->widget : $widget;

            return Admin::can(PermissionRegistry::widgetKey($class));
        }));
    }
}
