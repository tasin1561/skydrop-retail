<?php

namespace App\Filament\Support;

use App\Models\Setting;
use App\Support\Rbac\PermissionResolver;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Shared helpers for the admin panel: staff roles, runtime settings, marketplace links and status colours.
 */
class Admin
{
    /** Legacy users.role values (fallback when a staff member has no roles assigned). */
    public const ROLES = ['admin' => 'Admin', 'manager' => 'Manager', 'support' => 'Support', 'hub' => 'Hub staff'];

    public const MARKETS = ['amazon' => 'Amazon (amazon.in)', 'flipkart' => 'Flipkart (flipkart.com)', 'bigbasket' => 'BigBasket (bigbasket.com)'];

    public const ORDER_STATUS_LABELS = [
        'placed' => 'Placed', 'verified' => 'Verified', 'purchased' => 'Bought in India', 'india_hub' => 'At Kolkata hub',
        'in_transit' => 'Crossing border', 'dhaka_hub' => 'At Dhaka hub', 'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
    ];

    public const ORDER_STATUS_COLORS = [
        'placed' => 'gray', 'verified' => 'info', 'purchased' => 'primary', 'india_hub' => 'warning', 'in_transit' => 'warning',
        'dhaka_hub' => 'warning', 'out_for_delivery' => 'info', 'delivered' => 'success', 'cancelled' => 'danger',
    ];

    public const PAYMENT_STATUS_COLORS = [
        'pending' => 'warning', 'paid' => 'success', 'partial' => 'info', 'failed' => 'danger', 'refunded' => 'gray', 'refund_pending' => 'danger',
    ];

    /**
     * Runtime settings editable on the Settings page. Keys are stored in the `settings` table;
     * the value falls back to config("retail.{config}").
     *
     * @var array<string, string>
     */
    public const SETTINGS = [
        'oxylabs.monthly_budget_usd' => 'oxylabs.monthly_budget_usd',
        'oxylabs.hedge_after' => 'oxylabs.hedge_after',
        'variants.mode' => 'variants.mode',
        'variants.max' => 'variants.max',
        'prefetch_top' => 'prefetch_top',
        'ttl.search_fresh' => 'ttl.search_fresh',
        'ttl.search_stale' => 'ttl.search_stale',
        'ttl.product_fresh' => 'ttl.product_fresh',
        'ttl.product_stale' => 'ttl.product_stale',
        'ttl.sibling_fresh' => 'ttl.sibling_fresh',
        'ttl.details_fresh' => 'ttl.details_fresh',
        'pricing.shipping.base_bdt' => 'pricing.shipping.base_bdt',
        'pricing.shipping.per_kg_bdt' => 'pricing.shipping.per_kg_bdt',
        'pricing.shipping.free_above_bdt' => 'pricing.shipping.free_above_bdt',
        'pricing.fx_buffer' => 'pricing.fx_buffer',
        'alerts.telegram_chat' => 'alerts.telegram_chat',
        // Marketing tracking (App\Filament\Support\TrackingSettingsSection)
        'tracking.driver' => 'tracking.driver',
        'tracking.purchase_on' => 'tracking.purchase_on',
        'tracking.send_delivered' => 'tracking.send_delivered',
        'tracking.meta.pixel_id' => 'tracking.meta.pixel_id',
        'tracking.meta.access_token' => 'tracking.meta.access_token',
        'tracking.meta.test_event_code' => 'tracking.meta.test_event_code',
        'tracking.ga4.measurement_id' => 'tracking.ga4.measurement_id',
        'tracking.ga4.api_secret' => 'tracking.ga4.api_secret',
        'tracking.tiktok.pixel_id' => 'tracking.tiktok.pixel_id',
        'tracking.tiktok.access_token' => 'tracking.tiktok.access_token',
        'tracking.tiktok.test_event_code' => 'tracking.tiktok.test_event_code',
        'tracking.gtm_id' => 'tracking.gtm_id',
        'tracking.delivro.endpoint' => 'tracking.delivro.endpoint',
        'tracking.delivro.key' => 'tracking.delivro.key',
        'tracking.feed_key' => 'tracking.feed_key',
    ];

    /** A runtime setting: settings table first, then config/retail.php. */
    public static function setting(string $key, mixed $default = null): mixed
    {
        $fallback = config('retail.'.(self::SETTINGS[$key] ?? $key), $default);
        try {
            return Setting::get($key, $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * THE permission check for the admin (App\Support\Rbac). Keys: "{resource-slug}.{viewAny|view|create|update|delete|export}",
     * custom actions like "orders.refund", "page.{slug}", "widget.{slug}". Non-staff and blocked staff get false.
     */
    public static function can(string $key, ?Authenticatable $user = null): bool
    {
        $user ??= self::currentUser();

        return app(PermissionResolver::class)->can($user, $key);
    }

    /** Legacy role string (admin / manager / support / hub). Prefer Admin::can(). */
    public static function role(?Authenticatable $user = null): ?string
    {
        $user ??= self::currentUser();
        if (! $user || ! app(PermissionResolver::class)->isStaff($user)) {
            return null;
        }

        return $user->role ?: 'admin';
    }

    /** Legacy helper: manager-level actions inside sections the user can open (approve, mark paid, bulk delete, reset). */
    public static function canManage(?Authenticatable $user = null): bool
    {
        return self::can('actions.manage', $user);
    }

    /** Legacy helper: refunds and other money-moving order actions. */
    public static function isManager(?Authenticatable $user = null): bool
    {
        return self::can('orders.refund', $user);
    }

    /** Legacy helper: staff management (was role "admin"). */
    public static function isAdmin(?Authenticatable $user = null): bool
    {
        return self::can('staff.manage', $user);
    }

    private static function currentUser(): ?Authenticatable
    {
        try {
            return Filament::auth()->user() ?? auth()->user();
        } catch (Throwable) {
            return auth()->user();
        }
    }

    /** Public product page on the source marketplace. */
    public static function sourceUrl(string $market, string $productId): string
    {
        $id = rawurlencode($productId);

        return match ($market) {
            'amazon' => "https://www.amazon.in/dp/{$id}",
            'flipkart' => "https://www.flipkart.com/product/p/itm?pid={$id}",
            'bigbasket' => "https://www.bigbasket.com/pd/{$id}/",
            default => '#',
        };
    }

    public static function marketLabel(?string $market): string
    {
        return self::MARKETS[$market] ?? (string) $market;
    }

    /** Pretty, escaped JSON block for infolists (CodeEntry needs phiki/phiki, which is not installed). */
    public static function jsonHtml(mixed $data): HtmlString
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return new HtmlString('<pre style="max-height:32rem;overflow:auto;font-size:.75rem;line-height:1.45;padding:.75rem;border-radius:.5rem;background:rgba(127,127,127,.08);white-space:pre-wrap;word-break:break-word">'.e((string) $json).'</pre>');
    }

    public static function bdt(float|int|null $amount): string
    {
        return '৳'.number_format((float) $amount);
    }
}
