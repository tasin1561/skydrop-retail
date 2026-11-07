<?php

namespace App\Support\Rbac;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every permission key the admin knows about, discovered at runtime from the Filament "admin" panel plus custom keys.
 *
 *   Resources   {resource-slug}.{viewAny|view|create|update|delete|export}   e.g. orders.viewAny, flash-sales.delete
 *   Actions     {area}.{action}                                              e.g. orders.refund, customers.block
 *   Pages       page.{page-slug}                                             e.g. page.purchase-queue
 *   Widgets     widget.{kebab-class-name}                                    e.g. widget.order-stats-overview
 *
 * Modules add their own action keys from a service provider's boot():
 *
 *   PermissionRegistry::extend(['wallet.adjust' => 'Adjust wallet balance'], 'Wallet');
 *   PermissionRegistry::exportable('wallet-ledgers');   // adds the "export" ability to that resource's row
 */
class PermissionRegistry
{
    public const ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete'];

    /** Filament / Laravel policy abilities => the stored ability they check. */
    public const ABILITY_MAP = [
        'viewAny' => 'viewAny', 'view' => 'view', 'create' => 'create', 'replicate' => 'create',
        'update' => 'update', 'edit' => 'update', 'reorder' => 'update', 'restore' => 'update', 'restoreAny' => 'update',
        'attach' => 'update', 'detach' => 'update', 'detachAny' => 'update', 'associate' => 'update', 'dissociate' => 'update', 'dissociateAny' => 'update',
        'delete' => 'delete', 'deleteAny' => 'delete', 'forceDelete' => 'delete', 'forceDeleteAny' => 'delete',
        'export' => 'export',
    ];

    /** Resources whose matrix row also has an "export" column. */
    private static array $exportable = ['orders', 'customers', 'payments', 'products', 'activity-log', 'activity-logs'];

    /**
     * Built-in custom action keys, grouped by area.
     *
     * @var array<string, array<string, string>>
     */
    public const CUSTOM = [
        'Orders' => [
            'orders.refund' => 'Refund an order', 'orders.cancel' => 'Cancel an order', 'orders.set_status' => 'Set status manually',
            'orders.mark_purchased' => 'Mark purchased in India', 'orders.hand_to_skydrop' => 'Hand to Skydrop',
            'orders.scope.hub_only' => 'Restriction: only see orders at the purchased / Kolkata hub stage',
        ],
        'Customers' => [
            'customers.block' => 'Block / unblock customers', 'customers.adjust_points' => 'Adjust points', 'customers.message' => 'Message customers',
        ],
        'Finance' => [
            'payments.refund' => 'Refund a payment', 'reports.profit' => 'Profit reports', 'reports.accounting' => 'Accounting reports',
        ],
        'Catalog & pricing' => ['marketplaces.toggle' => 'Turn stores on / off, live fetch', 'pricing.manage' => 'Change pricing rules & FX', 'pricing.view' => 'View pricing'],
        'Marketing' => ['broadcast.send' => 'Send broadcasts', 'templates.manage' => 'Edit message templates'],
        'Fulfilment' => ['hub.receive' => 'Receive parcels at the hub', 'returns.resolve' => 'Resolve returns'],
        'Security' => ['fraud.override' => 'Override fraud checks'],
        'Assistant' => ['assistant.view' => 'View AI assistant conversations'],
        'System' => [
            'settings.manage' => 'Edit settings pages', 'features.manage' => 'Feature switches', 'staff.manage' => 'Manage staff accounts',
            'roles.manage' => 'Manage roles & permissions', 'actions.manage' => 'Manager actions inside allowed sections (approve, mark paid, bulk delete, reset)',
        ],
    ];

    /** @var array<string, array<string, string>> */
    private static array $extended = [];

    /** @param  array<string, string>|list<string>  $keys  key => label (or plain list of keys) */
    public static function extend(array $keys, string $group = 'Other'): void
    {
        foreach ($keys as $k => $label) {
            [$key, $label] = is_int($k) ? [$label, Str::headline(str_replace(['.', '_'], ' ', $label))] : [$k, $label];
            self::$extended[$group][$key] = $label;
        }
    }

    public static function exportable(string ...$slugs): void
    {
        self::$exportable = array_values(array_unique([...self::$exportable, ...$slugs]));
    }

    /**
     * Resource rows: slug => [label, group, model, abilities].
     *
     * @return array<string, array{label: string, group: string, model: ?string, class: string, abilities: list<string>}>
     */
    public static function resources(): array
    {
        $out = [];
        foreach (self::panelList('getResources') as $resource) {
            try {
                $slug = $resource::getSlug();
                $out[$slug] = [
                    'label' => (string) ($resource::getNavigationLabel() ?: Str::headline($slug)),
                    'group' => self::groupName($resource::getNavigationGroup()),
                    'model' => $resource::getModel(),
                    'class' => $resource,
                    'abilities' => in_array($slug, self::$exportable, true) ? [...self::ABILITIES, 'export'] : self::ABILITIES,
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $out;
    }

    /** @return array<string, array{label: string, group: string, class: string}> page.{slug} => meta */
    public static function pages(): array
    {
        $out = [];
        foreach (self::panelList('getPages') as $page) {
            if (is_subclass_of($page, ResourcePage::class) || is_a($page, Dashboard::class, true)) {
                continue;
            }
            try {
                $out[self::pageKey($page)] = [
                    'label' => (string) ($page::getNavigationLabel() ?: class_basename($page)),
                    'group' => self::groupName($page::getNavigationGroup()),
                    'class' => $page,
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $out;
    }

    /** @return array<string, array{label: string, class: string}> widget.{slug} => meta */
    public static function widgets(): array
    {
        $out = [];
        foreach (self::panelList('getWidgets') as $widget) {
            $class = is_object($widget) ? ($widget->widget ?? null) : $widget;
            if (is_string($class)) {
                $out[self::widgetKey($class)] = ['label' => Str::headline(class_basename($class)), 'class' => $class];
            }
        }

        return $out;
    }

    /** @return array<string, array<string, string>> group => [key => label], built-in and extended */
    public static function custom(): array
    {
        $out = self::CUSTOM;
        foreach (self::$extended as $group => $keys) {
            $out[$group] = ($out[$group] ?? []) + $keys;
        }

        return $out;
    }

    /** Every known key => label. */
    public static function all(): array
    {
        $out = [];
        foreach (self::resources() as $slug => $r) {
            foreach ($r['abilities'] as $a) {
                $out["$slug.$a"] = $r['label'].': '.$a;
            }
        }
        foreach (self::custom() as $keys) {
            $out += $keys;
        }
        foreach (self::pages() as $key => $p) {
            $out[$key] = 'Page: '.$p['label'];
        }
        foreach (self::widgets() as $key => $w) {
            $out[$key] = 'Widget: '.$w['label'];
        }

        return $out;
    }

    /** @return array<class-string, list<string>> model class => resource slugs */
    public static function modelMap(): array
    {
        $map = [];
        foreach (self::resources() as $slug => $r) {
            if ($r['model']) {
                $map[$r['model']][] = $slug;
            }
        }

        return $map;
    }

    public static function pageKey(string $page): string
    {
        return 'page.'.$page::getSlug();
    }

    public static function widgetKey(string $widget): string
    {
        return 'widget.'.Str::kebab(class_basename($widget));
    }

    private static function groupName(mixed $group): string
    {
        return match (true) {
            $group instanceof \UnitEnum && method_exists($group, 'getLabel') => (string) $group->getLabel(),
            $group instanceof \UnitEnum => $group->name,
            filled($group) => (string) $group,
            default => 'Other',
        };
    }

    /** @return list<mixed> */
    private static function panelList(string $method): array
    {
        try {
            return array_values(Filament::getPanel('admin')->{$method}());
        } catch (Throwable) {
            return [];
        }
    }
}
