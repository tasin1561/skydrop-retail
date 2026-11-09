<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\Assistant\AssistantSettings;
use App\Services\Catalog\RetailSettings;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Central system limits (Admin → System → Limits), built like App\Support\Features.
 *
 *   Limits::get('orders.max_qty')   Limits::int(...)   Limits::float(...)
 *
 * One source of truth per knob:
 *  - new limits are stored in the settings table as "limits.{key}" (cached 60 s);
 *  - limits another module already owned are BACKED: Limits reads and writes that module's own setting key
 *    (e.g. "oxylabs.monthly_budget_usd", "messaging.sms.per_phone_per_hour"), so the older admin pages keep working;
 *  - a default of "config:<key>" follows that config value (env-driven), e.g. security.limits.strict_ip.
 * A value of 0 means "no limit" wherever the help text says so.
 */
class Limits
{
    /**
     * key => [label, group, default, unit, min, max, help]
     *
     * @var array<string, array{0: string, 1: string, 2: int|float|string, 3: string, 4: int|float, 5: int|float, 6: string}>
     */
    public const REGISTRY = [
        // ---- Catalog & data spend
        'catalog.oxylabs_monthly_usd' => ['Oxylabs monthly budget', 'Catalog & data spend', 100, 'USD', 0, 100000,
            'At 100% browsing uses fallbacks and cache; Oxylabs is kept for checkout verification. Same value as Settings → Oxylabs budget.'],
        'catalog.oxylabs_daily_usd' => ['Oxylabs daily cap', 'Catalog & data spend', 10, 'USD', 0, 10000,
            'When today\'s spend reaches this, browsing serves cached data only; checkout verification still works. 0 = no daily cap.'],
        'catalog.verify_daily_usd' => ['Oxylabs price checks per day', 'Catalog & data spend', 3, 'USD', 0, 10000,
            'Daily ceiling for live price checks at checkout (approximate, from Oxylabs rates). Past it, orders use a price checked in the last 72 hours and are re-checked later. 0 = no ceiling.'],
        'catalog.store_daily_fetches' => ['Live fetches per store per day', 'Catalog & data spend', 5000, 'calls', 0, 1000000,
            'Provider calls per marketplace per day (all providers, checkout verification excluded). 0 = unlimited.'],
        'catalog.max_concurrent_fetches' => ['Max concurrent live fetches', 'Catalog & data spend', 10, 'requests', 1, 50,
            'Parallel provider requests in one batch (variant siblings, deals).'],
        'catalog.variant_max' => ['Variant fetches per product', 'Catalog & data spend', 60, 'products', 0, 500,
            'Sibling variants fetched for one product page. Same value as Settings → Variants max.'],
        'catalog.search_pages' => ['Store pages per search', 'Catalog & data spend', 5, 'pages', 1, 20,
            'Store result pages (~48 items each) fetched live for one query; deeper pages show what is cached.'],
        'catalog.product_ttl_min_minutes' => ['Minimum product refresh interval', 'Catalog & data spend', 30, 'minutes', 1, 10080,
            'A product is never re-fetched sooner than this, whatever the TTL settings say (including lightning deals).'],
        'catalog.search_ttl_min_minutes' => ['Minimum search refresh interval', 'Catalog & data spend', 60, 'minutes', 1, 10080,
            'A cached search result page is never re-fetched sooner than this.'],
        'catalog.searches_per_min' => ['Searches per IP per minute', 'Catalog & data spend', 'config:security.limits.strict_ip', 'requests', 1, 1000,
            'Search and suggest, per shopper IP (signed apps / direct clients; the Next.js server has its own bucket).'],
        'catalog.product_views_per_min' => ['Product views per IP per minute', 'Catalog & data spend', 'config:security.limits.strict_ip', 'requests', 1, 1000,
            'Product detail and price history, per shopper IP.'],
        'catalog.resolves_per_min' => ['Paste-link resolves per IP per minute', 'Catalog & data spend', 'config:security.limits.resolve_ip', 'requests', 1, 200,
            'POST /products/resolve (paste any link), per shopper IP.'],

        // ---- Product data plan (one shared monthly pool; see System → Product data usage)
        'data.capacity_amazon_calls' => ['Monthly plan: Amazon India calls', 'Product data plan', 220000, 'calls', 0, 100000000,
            'Successful Amazon India product-data calls the monthly plan covers when used for Amazon only. Amazon and Flipkart share one pool: 1 Flipkart call uses (Amazon calls ÷ Flipkart calls) of it. Failed calls never count. 0 = no plan.'],
        'data.capacity_flipkart_calls' => ['Monthly plan: Flipkart calls', 'Product data plan', 68000, 'calls', 0, 100000000,
            'Successful Flipkart calls the same plan covers when used for Flipkart only.'],
        'data.capacity_alert_percent' => ['Plan usage alert at', 'Product data plan', 80, '%', 1, 100,
            'Admins get one alert per billing cycle when usage passes this, and one more at 100%.'],
        'data.cycle_start_day' => ['Billing cycle starts on day', 'Product data plan', 1, 'day of month', 1, 28,
            'The plan resets on this day each month (Bangladesh time).'],

        // ---- Orders
        'orders.max_items' => ['Max items per cart', 'Orders', 50, 'lines', 1, 200, 'Distinct products in one quote / order.'],
        'orders.max_qty' => ['Max quantity per item', 'Orders', 20, 'pcs', 1, 99, 'Quantity of one product in one order.'],
        'orders.max_value_bdt' => ['Max order value', 'Orders', 500000, 'BDT', 0, 10000000, 'Order total. 0 = no maximum.'],
        'orders.min_value_bdt' => ['Min order value', 'Orders', 0, 'BDT', 0, 100000, 'Order total, checked when the order is placed. 0 = no minimum.'],
        'orders.max_per_day' => ['Orders per customer per day', 'Orders', 10, 'orders', 0, 1000, 'Placed orders (any status) since midnight. 0 = unlimited.'],
        'orders.max_partial_pay_bdt' => ['Max 50% advance order value', 'Orders', 50000, 'BDT', 0, 10000000,
            'Above this total the customer must prepay in full (bKash, Nagad or card). 0 = no cap.'],
        'orders.max_unpaid' => ['Max unpaid orders per customer', 'Orders', 5, 'orders', 0, 100,
            'Open orders still waiting for payment. 0 = unlimited.'],

        // ---- Accounts
        'accounts.otp_per_phone_hour' => ['OTP codes per phone per hour', 'Accounts', 3, 'codes', 1, 50, 'Plus a cooldown between codes (security settings).'],
        'accounts.otp_per_ip_hour' => ['OTP codes per IP per hour', 'Accounts', 10, 'codes', 1, 500, ''],
        'accounts.otp_wrong_lockout' => ['Wrong codes before lockout', 'Accounts', 'config:security.otp.wrong_lockout', 'attempts', 1, 50,
            'Wrong codes (across codes) before the phone is locked for the lockout period.'],
        'accounts.max_addresses' => ['Saved addresses per customer', 'Accounts', 10, 'addresses', 1, 100, ''],
        'accounts.max_wishlist' => ['Wishlist items per customer', 'Accounts', 200, 'items', 1, 5000, ''],
        'accounts.max_price_alerts' => ['Active price / stock alerts per customer', 'Accounts', 50, 'alerts', 1, 1000, ''],

        // ---- Messaging
        'messaging.sms_per_phone_hour' => ['SMS per phone per hour', 'Messaging', 8, 'SMS', 0, 100,
            'Non-OTP SMS. Same value as Messaging settings. 0 = unlimited.'],
        'messaging.marketing_sms_per_day' => ['Marketing SMS per day (total)', 'Messaging', 5000, 'SMS', 0, 1000000,
            'All marketing SMS sent today across customers. 0 = unlimited.'],
        'messaging.broadcast_max_recipients' => ['Broadcast recipients per run', 'Messaging', 20000, 'customers', 1, 1000000,
            'A broadcast stops after this many customers.'],

        // ---- AI assistant
        'assistant.guest_per_day' => ['Assistant messages per day (guest)', 'AI assistant', 10, 'messages', 0, 1000, 'Per IP. Same value as the AI assistant page.'],
        'assistant.user_per_day' => ['Assistant messages per day (signed in)', 'AI assistant', 40, 'messages', 0, 1000, ''],
        'assistant.plus_per_day' => ['Assistant messages per day (Plus)', 'AI assistant', 120, 'messages', 0, 5000, ''],
        'assistant.monthly_budget_usd' => ['Assistant monthly budget', 'AI assistant', 50, 'USD', 0, 100000, 'Over budget the assistant pauses.'],

        // ---- Uploads
        'uploads.image_mb' => ['Max image size', 'Uploads', 8, 'MB', 1, 50, 'Customer return photos and hub receipt photos.'],
        'uploads.video_mb' => ['Max video size', 'Uploads', 50, 'MB', 1, 500, 'Hub unboxing video.'],
        'uploads.max_files' => ['Max files per return / hub receipt', 'Uploads', 10, 'files', 1, 50, ''],

        // ---- API
        'api.per_ip_per_minute' => ['Public API requests per IP per minute', 'API', 300, 'requests', 30, 100000,
            'All /api/v1 requests per IP (the Next.js server, webhooks and health checks are exempt).'],
        'api.admin_session_minutes' => ['Admin idle session timeout', 'API', 'config:security.admin.session_timeout_minutes', 'minutes', 5, 1440,
            'Same value as Finance & security settings.'],
    ];

    /**
     * Limits owned by another module: [store, key]. Store: retail (RetailSettings), messaging, assistant, knobs (App\Support\Knobs).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const BACKED = [
        'catalog.oxylabs_monthly_usd' => ['retail', 'oxylabs.monthly_budget_usd'],
        'catalog.variant_max' => ['retail', 'variants.max'],
        'messaging.sms_per_phone_hour' => ['messaging', 'sms.per_phone_per_hour'],
        'assistant.guest_per_day' => ['assistant', 'limits.guest'],
        'assistant.user_per_day' => ['assistant', 'limits.user'],
        'assistant.plus_per_day' => ['assistant', 'limits.plus'],
        'assistant.monthly_budget_usd' => ['assistant', 'monthly_budget_usd'],
        'api.admin_session_minutes' => ['knobs', 'security.admin.session_timeout_minutes'],
    ];

    private const CACHE_KEY = 'limits:all';

    public static function get(string $key): int|float
    {
        if (! isset(self::REGISTRY[$key])) {
            throw new \InvalidArgumentException("Unknown limit [$key]");
        }
        if (isset(self::BACKED[$key])) {
            return self::number(self::readBacked($key), self::default($key));
        }

        return self::number(self::stored()[$key] ?? null, self::default($key));
    }

    public static function int(string $key): int
    {
        return (int) round(self::get($key));
    }

    public static function float(string $key): float
    {
        return (float) self::get($key);
    }

    /** Registry default, resolving "config:<key>". */
    public static function default(string $key): int|float
    {
        $d = self::REGISTRY[$key][2];
        if (is_string($d) && str_starts_with($d, 'config:')) {
            $d = config(substr($d, 7));
        }

        return self::number($d, 0);
    }

    /** True when the stored value differs from the default (or is explicitly stored). */
    public static function overridden(string $key): bool
    {
        return self::get($key) != self::default($key);
    }

    /** Sets a limit (clamped to min..max); null resets it to the default. */
    public static function set(string $key, int|float|string|null $value): void
    {
        [, , , , $min, $max] = self::REGISTRY[$key] ?? throw new \InvalidArgumentException("Unknown limit [$key]");
        if ($value !== null && $value !== '') {
            $value = max($min, min($max, self::number($value, 0)));
        } else {
            $value = null;
        }

        if (isset(self::BACKED[$key])) {
            self::writeBacked($key, $value);
        } elseif ($value === null) {
            Setting::query()->whereKey("limits.$key")->delete();
        } else {
            Setting::put("limits.$key", $value);
        }
        self::flush();
    }

    public static function reset(string $key): void
    {
        self::set($key, null);
    }

    /** @return array<string, int|float> every limit's current value */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::REGISTRY) as $key) {
            $out[$key] = self::get($key);
        }

        return $out;
    }

    /** @return array<string, array<string, array<string, mixed>>> group => key => meta + value */
    public static function groups(): array
    {
        $out = [];
        foreach (self::REGISTRY as $key => [$label, $group, , $unit, $min, $max, $help]) {
            $out[$group][$key] = ['label' => $label, 'unit' => $unit, 'min' => $min, 'max' => $max, 'help' => $help,
                'default' => self::default($key), 'value' => self::get($key), 'backed' => self::BACKED[$key][1] ?? null];
        }

        return $out;
    }

    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
        }
        Knobs::forget();
    }

    /** @return array<string, mixed> stored limits.* values */
    private static function stored(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 60, function () {
                $out = [];
                foreach (Setting::query()->where('key', 'like', 'limits.%')->get() as $row) {
                    $out[substr($row->key, 7)] = $row->value;
                }

                return $out;
            });
        } catch (Throwable) {
            return []; // settings table missing (fresh install): defaults
        }
    }

    private static function readBacked(string $key): mixed
    {
        [$store, $path] = self::BACKED[$key];
        $default = self::default($key);
        try {
            return match ($store) {
                'retail' => RetailSettings::get($path, $default),
                'messaging' => MessagingSettings::get($path, $default),
                'assistant' => AssistantSettings::get($path, $default),
                'knobs' => Knobs::get($path, $default),
                default => $default,
            };
        } catch (Throwable) {
            return $default;
        }
    }

    private static function writeBacked(string $key, int|float|null $value): void
    {
        [$store, $path] = self::BACKED[$key];
        match ($store) {
            'retail' => self::putRetail($path, $value),
            'messaging' => MessagingSettings::put($path, $value),
            'assistant' => AssistantSettings::put($path, $value),
            'knobs' => Knobs::put($path, $value),
            default => null,
        };
    }

    private static function putRetail(string $path, int|float|null $value): void
    {
        if ($value === null) {
            Setting::query()->whereKey($path)->delete();
        } else {
            Setting::put($path, $value);
        }
        Cache::forget("retail-setting:$path");
    }

    private static function number(mixed $v, int|float $fallback): int|float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return $fallback;
        }
        $f = (float) $v;

        return floor($f) == $f && abs($f) < PHP_INT_MAX ? (int) $f : $f;
    }
}
