<?php

namespace App\Support\Rbac;

use App\Filament\Support\Admin;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Row-level scoping for admin resources, switched on by a restriction permission (".scope." keys are never granted
 * by wildcards, so Super Admin / Manager are unaffected).
 *
 *   ResourceScopes::register('orders', 'orders.scope.hub_only', fn (Builder $q) => $q->whereIn('status', [...]));
 *   // in the resource: public static function getEloquentQuery(): Builder
 *   //                  { return ResourceScopes::apply('orders', parent::getEloquentQuery()); }
 */
class ResourceScopes
{
    /** @var array<string, array<string, Closure>> */
    private static array $scopes = [];

    public static function register(string $resourceSlug, string $permission, Closure $scope): void
    {
        self::$scopes[$resourceSlug][$permission] = $scope;
    }

    public static function apply(string $resourceSlug, Builder $query): Builder
    {
        foreach (self::$scopes[$resourceSlug] ?? [] as $permission => $scope) {
            if (Admin::can($permission)) {
                $scope($query);
            }
        }

        return $query;
    }
}
