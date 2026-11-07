<?php

namespace App\Filament\Concerns;

use App\Filament\Support\Admin;
use App\Support\Rbac\PermissionRegistry;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Checks "{slug}.{ability}" directly instead of going through the Gate. Only needed for resources that share a model
 * with another resource (Staff and Customers both use User); every other resource is covered automatically by the
 * Gate::before hook in RbacServiceProvider.
 */
trait HasResourcePermissions
{
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        $ability = PermissionRegistry::ABILITY_MAP[$action] ?? $action;

        return Admin::can(static::getSlug().'.'.$ability) ? Response::allow() : Response::deny();
    }
}
