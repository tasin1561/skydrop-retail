<?php

namespace App\Support\Rbac;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Role assignment with the "last Super Admin" safety net, and activity logging of role / permission changes. */
class RoleGuard
{
    public const LOGGER = 'App\\Services\\Audit\\ActivityLogger';

    /** Active Super Admins: pivot role super_admin, or legacy is_admin users without roles whose role is admin/null. */
    public static function superAdminIds(): array
    {
        $roleId = Role::query()->where('slug', Role::SUPER_ADMIN)->value('id');
        $pivot = $roleId ? DB::table('role_user')->where('role_id', $roleId)->pluck('user_id')->all() : [];
        $legacy = User::query()->where('is_admin', true)->whereNull('blocked_at')
            ->where(fn ($q) => $q->whereNull('role')->orWhere('role', 'admin'))
            ->whereNotIn('id', DB::table('role_user')->select('user_id'))->pluck('id')->all();
        $active = User::query()->whereIn('id', $pivot)->whereNull('blocked_at')->pluck('id')->all();

        return array_values(array_unique([...$active, ...$legacy]));
    }

    public static function isLastSuperAdmin(User $user): bool
    {
        $ids = self::superAdminIds();

        return in_array($user->id, $ids, true) && count($ids) <= 1;
    }

    /** @param  list<int|string>  $roleIds */
    public static function syncRoles(User $user, array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_map('intval', array_filter($roleIds))));
        $before = $user->exists ? $user->roles()->pluck('roles.id')->map(fn ($i) => (int) $i)->all() : [];
        sort($before);
        sort($roleIds);
        if ($before === $roleIds && $before !== []) {
            return;
        }

        $superId = (int) Role::query()->where('slug', Role::SUPER_ADMIN)->value('id');
        if (! in_array($superId, $roleIds, true) && self::isLastSuperAdmin($user)) {
            throw new RuntimeException('This is the last Super Admin. Give another staff member the Super Admin role first.');
        }

        $user->roles()->sync($roleIds);
        // Keep the legacy column meaningful for code that still reads it.
        $slugs = Role::query()->whereIn('id', $roleIds)->pluck('slug')->all();
        $legacy = array_search(collect(DefaultRoles::LEGACY)->intersect($slugs)->first(), DefaultRoles::LEGACY, true) ?: null;
        $user->forceFill(['role' => $legacy ?? ($slugs ? 'support' : $user->role), 'is_admin' => $user->is_admin || $roleIds !== []])->saveQuietly();
        app(PermissionResolver::class)->flush();

        $names = fn (array $ids) => Role::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
        self::log('staff.roles_changed', $user, ['roles' => [$names($before), $names($roleIds)]], "Changed roles of {$user->name}");
    }

    /** Refuse blocking / deleting / un-staffing the last Super Admin. */
    public static function assertCanRemove(User $user): void
    {
        if (self::isLastSuperAdmin($user)) {
            throw new RuntimeException('This is the last Super Admin and cannot be removed, blocked or demoted.');
        }
    }

    /** ActivityLogger when the audit module is installed. */
    public static function log(string $action, ?Model $subject, array $changes, string $description): void
    {
        if (class_exists(self::LOGGER) && method_exists(self::LOGGER, 'log')) {
            rescue(fn () => (self::LOGGER)::log($action, $subject, $changes, $description), null, true);
        }
    }
}
