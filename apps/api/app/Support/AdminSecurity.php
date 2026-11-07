<?php

namespace App\Support;

use App\Filament\Support\Admin;
use Filament\Facades\Filament;

/** Admin hardening switches read by the panel provider. */
class AdminSecurity
{
    /** 2FA is mandatory for admin and manager roles (ADMIN_REQUIRE_2FA / runtime setting). */
    public static function requires2fa(): bool
    {
        if (! Knobs::bool('security.admin.require_2fa', (bool) config('security.admin.require_2fa'))) {
            return false;
        }
        $user = Filament::auth()->user();
        if (! $user) {
            return false;
        }

        return in_array(Admin::role($user), ['admin', 'manager'], true);
    }

    /** RBAC-aware permission check: Admin::can() when the RBAC module provides it, else admin/manager. */
    public static function can(string $permission): bool
    {
        if (method_exists(Admin::class, 'can')) {
            return (bool) Admin::can($permission);
        }

        return Admin::canManage();
    }
}
