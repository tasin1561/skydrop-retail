<?php

namespace App\Providers;

use App\Models\Marketplace;
use App\Models\Role;
use App\Models\User;
use App\Support\FeatureSync;
use App\Support\Rbac\PermissionRegistry;
use App\Support\Rbac\PermissionResolver;
use App\Support\Rbac\ResourceScopes;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Staff RBAC for the Filament admin (see App\Support\Rbac) and feature-switch syncing.
 *
 * Gate::before maps a policy check on a model to its Filament resource: ability "delete" on App\Models\Coupon inside
 * the admin panel checks permission "coupons.delete". Resources added later are covered automatically. Non-staff
 * users, checks outside the admin panel and models without a resource fall through (null) to normal policies.
 */
class RbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PermissionResolver::class);
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if (! $user instanceof User || Filament::getCurrentPanel()?->getId() !== 'admin') {
                return null;
            }
            $target = $arguments[0] ?? null;
            $model = $target instanceof Model ? $target::class : (is_string($target) && class_exists($target) ? $target : null);
            $mapped = PermissionRegistry::ABILITY_MAP[$ability] ?? null;
            if (! $model || ! $mapped) {
                return null;
            }
            $resolver = app(PermissionResolver::class);
            $slugs = $resolver->resourceSlugsFor($model);
            if ($slugs === [] || ! $resolver->isStaff($user)) {
                return null;
            }
            foreach ($slugs as $slug) {
                if ($resolver->can($user, "$slug.$mapped")) {
                    // Allowed: let a real policy (business rules) still have its say when one exists.
                    return Gate::getPolicyFor($model) ? null : true;
                }
            }

            return false;
        });

        // Hub staff only see orders waiting at / heading to the Kolkata hub.
        ResourceScopes::register('orders', 'orders.scope.hub_only', fn (Builder $q) => $q->whereIn($q->qualifyColumn('status'), ['purchased', 'india_hub']));

        Role::saved(fn () => app(PermissionResolver::class)->flush());
        Role::deleted(fn () => app(PermissionResolver::class)->flush());

        // marketplaces.enabled <-> features.store.{id}
        Marketplace::saved(fn (Marketplace $m) => $m->wasChanged('enabled') ? FeatureSync::fromMarketplace($m) : null);
    }
}
