<?php

namespace App\Models\Concerns;

use App\Models\Role;
use App\Support\Rbac\PermissionResolver;
use App\Support\Rbac\RoleGuard;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Staff roles on the User model (App\Support\Rbac). */
trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /** Assign exactly these roles. Refuses to remove the last Super Admin. Logs the change. */
    public function syncRoles(array $roleIds): void
    {
        RoleGuard::syncRoles($this, $roleIds);
    }

    public function hasPermission(string $key): bool
    {
        return app(PermissionResolver::class)->can($this, $key);
    }

    public function isSuperAdmin(): bool
    {
        return in_array(Role::SUPER_ADMIN, app(PermissionResolver::class)->roleSlugs($this), true);
    }
}
