<?php

namespace App\Support\Rbac;

use App\Models\Role;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves a staff member's roles and answers permission checks. Registered as a scoped singleton, so results are
 * memoised for one request (or one test) only; call flush() after changing roles.
 *
 * Pattern rules (per role): a key is granted when it matches a positive entry and no "!" entry of that role.
 * "*" and "x.*" wildcards never match restriction keys containing ".scope." — those must be granted exactly.
 */
class PermissionResolver
{
    /** @var array<int|string, list<array{slug: string, name: string, permissions: list<string>}>> */
    private array $roles = [];

    /** @var array<string, bool> */
    private array $checks = [];

    private ?bool $hasTables = null;

    public function can(?Authenticatable $user, string $key): bool
    {
        if (! $user || ! $this->isStaff($user)) {
            return false;
        }
        $memo = $user->getAuthIdentifier().'|'.$key;

        return $this->checks[$memo] ??= collect($this->rolesFor($user))->contains(fn ($r) => self::roleGrants($r['permissions'], $key));
    }

    /** Staff = is_admin or holds at least one role; blocked staff are never staff. */
    public function isStaff(Authenticatable $user): bool
    {
        if (! empty($user->blocked_at)) {
            return false;
        }

        return (bool) ($user->is_admin ?? false) || $this->rolesFor($user, false) !== [];
    }

    /** @return list<string> */
    public function roleSlugs(Authenticatable $user): array
    {
        return array_column($this->rolesFor($user), 'slug');
    }

    /**
     * Roles in effect: the role_user pivot, else the legacy users.role string (null on an is_admin user = admin).
     *
     * @return list<array{slug: string, name: string, permissions: list<string>}>
     */
    public function rolesFor(Authenticatable $user, bool $legacy = true): array
    {
        $id = $user->getAuthIdentifier();
        if (! $legacy) {
            return $this->pivotRoles($user);
        }
        if (isset($this->roles[$id])) {
            return $this->roles[$id];
        }

        $roles = $this->pivotRoles($user);
        if ($roles === [] && ($user->is_admin ?? false)) {
            $slug = DefaultRoles::LEGACY[$user->role ?? null ?: 'admin'] ?? null;
            if ($slug) {
                $row = $this->tablesExist() ? rescue(fn () => Role::query()->where('slug', $slug)->first(), null, false) : null;
                $def = DefaultRoles::all()[$slug];
                $roles = [[
                    'slug' => $slug,
                    'name' => ($row->name ?? $def['name']).' (legacy role)',
                    'permissions' => array_values($row->permissions ?? $def['permissions']),
                ]];
            }
        }

        return $this->roles[$id] = $roles;
    }

    /** @return list<array{slug: string, name: string, permissions: list<string>}> */
    private function pivotRoles(Authenticatable $user): array
    {
        if (! $this->tablesExist() || ! method_exists($user, 'roles') || ! $user->getAuthIdentifier()) {
            return [];
        }
        $key = 'pivot:'.$user->getAuthIdentifier();
        if (isset($this->roles[$key])) {
            return $this->roles[$key];
        }
        try {
            $rows = $user->roles()->get(['roles.id', 'roles.slug', 'roles.name', 'roles.permissions']);
        } catch (Throwable) {
            return [];
        }

        return $this->roles[$key] = $rows->map(fn (Role $r) => ['slug' => $r->slug, 'name' => $r->name, 'permissions' => array_values($r->permissions ?? [])])->all();
    }

    /**
     * Which roles grant each key: key => [role names]. Keys no role grants map to [].
     *
     * @param  list<string>  $keys
     * @return array<string, list<string>>
     */
    public function explain(Authenticatable $user, array $keys): array
    {
        $roles = $this->rolesFor($user);
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = array_values(array_map(fn ($r) => $r['name'], array_filter($roles, fn ($r) => self::roleGrants($r['permissions'], $key))));
        }

        return $out;
    }

    /** @param  list<string>  $patterns */
    public static function roleGrants(array $patterns, string $key): bool
    {
        $granted = false;
        foreach ($patterns as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            if (str_starts_with($p, '!')) {
                if (self::matches(substr($p, 1), $key)) {
                    return false;
                }

                continue;
            }
            $granted = $granted || self::matches($p, $key);
        }

        return $granted;
    }

    public static function matches(string $pattern, string $key): bool
    {
        if ($pattern === $key) {
            return true;
        }
        if (! str_contains($pattern, '*') || str_contains($key, '.scope.')) {
            return false;
        }

        return Str::is($pattern, $key);
    }

    /** @var array<class-string, list<string>>|null */
    private ?array $modelMap = null;

    /** Resource slugs registered for a model class (memoised PermissionRegistry::modelMap()). */
    public function resourceSlugsFor(string $model): array
    {
        $this->modelMap ??= PermissionRegistry::modelMap();

        return $this->modelMap[$model] ?? [];
    }

    public function flush(): void
    {
        $this->roles = [];
        $this->checks = [];
    }

    private function tablesExist(): bool
    {
        return $this->hasTables ??= rescue(fn () => Schema::hasTable('roles') && Schema::hasTable('role_user'), false, false);
    }
}
