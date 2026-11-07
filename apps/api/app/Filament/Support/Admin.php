<?php

namespace App\Filament\Support;

use App\Models\Setting;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Shared helpers for the admin panel: staff roles, runtime settings, marketplace links and status colours.
 */
class Admin
{
    public const ROLES = ['admin' => 'Admin', 'manager' => 'Manager', 'support' => 'Support'];

    public const MARKETS = ['amazon' => 'Mega Store (amazon.in)', 'flipkart' => 'Style Bazaar (flipkart.com)', 'bigbasket' => 'Daily Basket (bigbasket.com)'];

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

    public static function role(?Authenticatable $user = null): ?string
    {
        $user ??= Filament::auth()->user();
        if (! $user || ! $user->is_admin) {
            return null;
        }

        return $user->role ?: 'admin';
    }

    /** Destructive actions (cancel, refund, delete, points adjust, blocking, settings) need admin or manager. */
    public static function canManage(?Authenticatable $user = null): bool
    {
        return in_array(self::role($user), ['admin', 'manager'], true);
    }

    public static function isAdmin(?Authenticatable $user = null): bool
    {
        return self::role($user) === 'admin';
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
