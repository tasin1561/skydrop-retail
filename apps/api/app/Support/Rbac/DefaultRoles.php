<?php

namespace App\Support\Rbac;

/**
 * Seeded staff roles. Permission entries are exact keys ("orders.refund"), wildcards ("orders.*", "page.hub*", "*")
 * or denials prefixed with "!" ("!staff.*"). A denial only cancels grants from the same role. Wildcards never match
 * restriction keys ("*.scope.*"), so those must be listed exactly.
 *
 * The migration seeds these; PermissionResolver also falls back to them while the roles table does not exist yet.
 */
class DefaultRoles
{
    /** Legacy users.role value => role slug. */
    public const LEGACY = ['admin' => 'super_admin', 'manager' => 'manager', 'support' => 'support', 'hub' => 'hub_staff'];

    /** @return array<string, array{name: string, description: string, permissions: list<string>}> */
    public static function all(): array
    {
        return [
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Everything, including staff, roles and feature switches. Cannot be deleted.',
                'permissions' => ['*'],
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Runs the shop: everything except staff, roles, feature switches and security settings.',
                'permissions' => ['*', '!roles.*', '!staff.*', '!features.*', '!page.features', '!security*', '!page.security*', '!settings.security*'],
            ],
            'order_ops' => [
                'name' => 'Order Ops',
                'description' => 'Verifies, buys and ships orders; purchase queue. Read-only customers and payments.',
                'permissions' => [
                    'orders.viewAny', 'orders.view', 'orders.update', 'orders.export', 'orders.set_status', 'orders.mark_purchased',
                    'orders.hand_to_skydrop', 'orders.cancel', 'page.purchase-queue', 'customers.viewAny', 'customers.view',
                    'payments.viewAny', 'payments.view', 'widget.order-stats-overview', 'widget.orders-by-status-chart',
                    'widget.orders-by-marketplace-chart',
                ],
            ],
            'support' => [
                'name' => 'Support',
                'description' => 'Customer care: customers, support requests, reviews, order lookup, returns (view), messaging customers.',
                'permissions' => [
                    'customers.viewAny', 'customers.view', 'customers.update', 'customers.message', 'orders.viewAny', 'orders.view',
                    'support-requests.*', 'support*', 'product-reviews.*', 'reviews*', 'returns.viewAny', 'returns.view',
                    'page.purchase-queue', 'widget.order-stats-overview',
                ],
            ],
            'hub_staff' => [
                'name' => 'Hub Staff',
                'description' => 'Kolkata hub: hub pages and receiving. Sees only orders at the purchased / Kolkata hub stage.',
                'permissions' => ['hub*', 'page.hub*', 'widget.hub*', 'orders.viewAny', 'orders.view', 'orders.hand_to_skydrop', 'orders.scope.hub_only'],
            ],
            'finance' => [
                'name' => 'Finance',
                'description' => 'Payments, refunds, profit and accounting reports, affiliate payouts.',
                'permissions' => [
                    'payments.*', 'orders.viewAny', 'orders.view', 'orders.export', 'orders.refund', 'refunds*', 'payouts*',
                    'affiliate-payouts.*', 'affiliate-commissions.*', 'finance*', 'page.finance*', 'page.*report*', 'page.*profit*',
                    'page.*accounting*', 'reports.*', 'wallet*', 'fx-rates.viewAny', 'fx-rates.view', 'widget.*', 'actions.manage',
                ],
            ],
            'marketing' => [
                'name' => 'Marketing',
                'description' => 'Banners, flash sales, coupons, broadcasts, affiliates and tracking settings.',
                'permissions' => [
                    'banners.*', 'flash-sales.*', 'coupons.*', 'broadcast.send', 'page.broadcast', 'affiliates.*', 'affiliate-*',
                    'tracking*', 'page.tracking*', 'page.growth-settings', 'message-templates.*', 'templates.manage', 'customers.viewAny',
                    'widget.sales-by-source-chart', 'widget.top-affiliates', 'actions.manage',
                ],
            ],
            'catalog' => [
                'name' => 'Catalog',
                'description' => 'Products and categories; pricing rules and marketplaces read-only.',
                'permissions' => [
                    'products.*', 'categories.*', 'restricted-rules.*', 'product-watches.*', 'pricing-rules.viewAny', 'pricing-rules.view',
                    'pricing.view', 'marketplaces.viewAny', 'marketplaces.view', 'fx-rates.viewAny', 'fx-rates.view', 'bundles*',
                    'widget.low-margin-alerts', 'actions.manage',
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function permissionsFor(string $slug): array
    {
        return self::all()[$slug]['permissions'] ?? [];
    }
}
