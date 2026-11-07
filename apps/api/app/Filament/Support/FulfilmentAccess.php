<?php

namespace App\Filament\Support;

/**
 * Permission checks for the fulfilment admin (hub pages, returns). Uses the RBAC permission keys
 * ("hub.receive", "returns.resolve") through Admin::can() once the roles+permissions module provides it.
 */
class FulfilmentAccess
{
    public static function can(string $permission): bool
    {
        if (method_exists(Admin::class, 'can')) {
            return (bool) Admin::can($permission);
        }

        // TODO(rbac): remove this legacy fallback once Admin::can() exists. Until then: admins and managers only.
        return Admin::canManage();
    }

    public static function hub(): bool
    {
        return self::can('hub.receive');
    }

    public static function returns(): bool
    {
        return self::can('returns.resolve');
    }
}
