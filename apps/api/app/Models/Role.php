<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RuntimeException;

/**
 * Staff role with a list of permission keys (see App\Support\Rbac\DefaultRoles for the pattern syntax).
 *
 * @property list<string> $permissions
 */
class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (Role $role) {
            if ($role->slug === self::SUPER_ADMIN) {
                throw new RuntimeException('The Super Admin role cannot be deleted.');
            }
        });
        static::saving(function (Role $role) {
            if ($role->exists && $role->getOriginal('slug') === self::SUPER_ADMIN) {
                // Super Admin always keeps every permission and its slug.
                $role->slug = self::SUPER_ADMIN;
                $role->permissions = ['*'];
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SUPER_ADMIN;
    }
}
