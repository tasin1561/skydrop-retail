<?php

namespace App\Providers;

use App\Support\Rbac\PermissionRegistry;
use Illuminate\Support\ServiceProvider;

/** Shopping module: price history, compare stores, bundles, group buy, pre-orders, product requests. */
class ShoppingServiceProvider extends ServiceProvider
{
    public const PERMISSIONS = [
        'matches.review' => 'Confirm or reject store matches',
        'matches.run' => 'Run the store matcher',
        'groups.close' => 'Force-close group buys (places orders)',
        'groups.cancel' => 'Cancel group buys',
        'preorders.allow' => 'Open pre-orders from the waitlist report',
        'requests.quote' => 'Send quotes for product requests',
        'requests.status' => 'Change product request status',
    ];

    public function boot(): void
    {
        if (class_exists(PermissionRegistry::class) && method_exists(PermissionRegistry::class, 'extend')) {
            PermissionRegistry::extend(self::PERMISSIONS, 'Shopping');
        }
    }
}
