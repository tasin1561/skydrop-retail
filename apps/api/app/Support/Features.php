<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Central feature switches. Every optional feature is gated here and toggled from
 * Admin → System → Features. Stored in the `settings` table under "features.{key}".
 *
 * Server: Features::enabled('affiliate') / route middleware "feature:affiliate".
 * Clients: GET /api/v1/features → {"features": {"affiliate": true, ...}}.
 */
class Features
{
    /**
     * key => [label, group, default, description]
     *
     * @var array<string, array{0: string, 1: string, 2: bool, 3: string}>
     */
    public const REGISTRY = [
        // Stores
        'store.amazon' => ['Mega Store (amazon.in)', 'Stores', true, 'Also controlled by the marketplace switch.'],
        'store.flipkart' => ['Style Bazaar (flipkart.com)', 'Stores', true, 'Also controlled by the marketplace switch.'],
        'store.bigbasket' => ['Daily Basket (bigbasket.com)', 'Stores', true, 'Also controlled by the marketplace switch.'],
        'paste_link' => ['Paste any link', 'Shopping', true, 'Customers paste an amazon.in / Flipkart / BigBasket link.'],
        'live_fetch' => ['Live product fetching', 'Shopping', true, 'Off = only products already in our database.'],
        'offers' => ['Offers & live deals', 'Shopping', true, ''],
        'price_history' => ['Price history chart', 'Shopping', true, ''],
        'compare_stores' => ['Compare stores', 'Shopping', true, ''],
        'bundles' => ['Bundles & frequently bought together', 'Shopping', true, ''],
        'group_orders' => ['Group buy', 'Shopping', true, ''],
        'preorders' => ['Pre-orders', 'Shopping', true, ''],
        'waitlist' => ['Restock waitlist & price alerts', 'Shopping', true, ''],
        'product_requests' => ['Request a product', 'Shopping', true, ''],
        'reviews' => ['Reviews', 'Shopping', true, ''],
        'flash_sales' => ['Flash sales', 'Shopping', true, ''],
        'banners' => ['Home banners', 'Shopping', true, ''],
        'restrictions' => ['Restricted items check', 'Shopping', true, 'Block/warn items that cannot ship to Bangladesh.'],
        'ai_assistant' => ['AI shopping assistant', 'Shopping', false, 'Uses the Claude API (costs money).'],
        'bangla' => ['Bangla language', 'Shopping', true, ''],
        // Checkout & payments
        'pay.bkash' => ['bKash', 'Payments', true, ''],
        'pay.nagad' => ['Nagad', 'Payments', true, ''],
        'pay.card' => ['Card (SSLCommerz)', 'Payments', true, ''],
        'pay.advance50' => ['50% advance, rest on delivery', 'Payments', true, ''],
        'pay.emi' => ['Card EMI', 'Payments', false, ''],
        'coupons' => ['Coupons', 'Payments', true, ''],
        'points' => ['Skydrop Points', 'Payments', true, ''],
        'wallet' => ['Store credit wallet', 'Payments', true, ''],
        'gift_cards' => ['Gift cards', 'Payments', true, ''],
        'membership' => ['Skydrop Plus membership', 'Payments', false, ''],
        'unboxing' => ['Paid unboxing at Kolkata hub', 'Fulfilment', true, ''],
        'returns' => ['Returns & replacements', 'Fulfilment', true, ''],
        'live_tracking_map' => ['Live tracking map', 'Fulfilment', true, ''],
        'supplier_price_alerts' => ['Supplier price alerts', 'Fulfilment', true, ''],
        'fraud_check' => ['Fraud check', 'Fulfilment', true, ''],
        // Growth & messaging
        'affiliate' => ['Affiliate program', 'Growth', true, ''],
        'referral' => ['Refer a friend', 'Growth', true, ''],
        'share' => ['Share buttons', 'Growth', true, ''],
        'tracking' => ['Pixels & server-side tracking', 'Growth', true, ''],
        'feeds' => ['Product feeds (Meta / Google)', 'Growth', true, ''],
        'sms' => ['SMS', 'Messaging', true, 'OTP SMS is always sent.'],
        'email' => ['Email', 'Messaging', true, ''],
        'push' => ['Push notifications', 'Messaging', true, ''],
        'in_app_notifications' => ['In-app notifications', 'Messaging', true, ''],
        'abandoned_cart' => ['Abandoned cart reminders', 'Messaging', true, ''],
        'marketing_messages' => ['Marketing messages & broadcasts', 'Messaging', true, ''],
        'support_chat' => ['WhatsApp / Messenger chat button', 'Messaging', true, ''],
        // Platform
        'signups' => ['New customer sign-ups', 'Platform', true, 'Off = existing customers only.'],
        'checkout' => ['Checkout', 'Platform', true, 'Off = browsing only (maintenance / holidays).'],
        'mobile_app' => ['Mobile app access', 'Platform', true, ''],
    ];

    private const CACHE_KEY = 'features:all';

    public static function enabled(string $key): bool
    {
        return (bool) (static::all()[$key] ?? false);
    }

    /** @return array<string, bool> */
    public static function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 60, function () {
                $out = [];
                foreach (self::REGISTRY as $key => [, , $default]) {
                    $out[$key] = (bool) Setting::get("features.$key", $default);
                }

                return $out;
            });
        } catch (Throwable) {
            return array_map(fn ($r) => $r[2], self::REGISTRY);
        }
    }

    public static function set(string $key, bool $on): void
    {
        Setting::put("features.$key", $on);
        static::flush();
        FeatureSync::fromFeature($key, $on); // store.* <-> marketplaces.enabled
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
