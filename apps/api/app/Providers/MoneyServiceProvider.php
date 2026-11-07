<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Money module wiring: admin permission keys for wallet, gift card and Skydrop Plus actions (RBAC matrix).
// Schedules live in routes/console.php ("money:*").
class MoneyServiceProvider extends ServiceProvider
{
    public const PERMISSIONS = [
        'wallet.adjust' => 'Credit / debit a customer wallet',
        'giftcards.issue' => 'Issue gift cards',
        'giftcards.bulk' => 'Bulk-generate promo gift cards',
        'giftcards.void' => 'Void gift cards',
        'membership.grant' => 'Grant Skydrop Plus',
        'membership.extend' => 'Extend Skydrop Plus',
        'membership.cancel' => 'Cancel Skydrop Plus',
    ];

    public function boot(): void
    {
        if (class_exists(\App\Support\Rbac\PermissionRegistry::class) && method_exists(\App\Support\Rbac\PermissionRegistry::class, 'extend')) {
            \App\Support\Rbac\PermissionRegistry::extend(self::PERMISSIONS, 'Wallet, gift cards & Plus');
        }
    }
}
