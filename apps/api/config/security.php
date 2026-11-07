<?php

// Security module (API protection, bot defence, admin hardening). See docs/SECURITY.md.
// Keys under "runtime" can be overridden from Admin → System → Finance & security (settings table, App\Support\Knobs).

$testing = env('APP_ENV') === 'testing';

/** "id:secret,id2:secret2" → [id => secret] */
$pairs = static function (?string $raw): array {
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', (string) $raw))) as $pair) {
        [$id, $secret] = array_pad(explode(':', $pair, 2), 2, '');
        if ($id !== '' && $secret !== '') {
            $out[$id] = $secret;
        }
    }

    return $out;
};

return [
    // Master switch for request guards (catalog guard, bot guard, progressive rate limits, OTP guard, admin 2FA).
    // Off by default under phpunit so the module test suites run unchanged; the security tests turn it on.
    'enforce' => (bool) env('SECURITY_ENFORCE', ! $testing),

    // Server-to-server key: the Next.js server sends it as X-Internal-Key.
    'internal_key' => env('INTERNAL_API_KEY'),

    // Signed app clients (mobile): X-App-Key: <id>, X-App-Timestamp: <unix>, X-App-Signature: hex(hmac_sha256(secret, canonical)).
    'app_keys' => $pairs(env('APP_SIGNING_KEYS')),
    'signature_skew' => 300, // seconds

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'pass_ttl' => 3600, // a solved challenge clears an IP for this long
    ],

    // Per-minute limits (RateLimiter). "strict" covers search, suggest, resolve, product detail and price history.
    'limits' => [
        'catalog_ip' => 120,
        'strict_ip' => 40,
        'strict_user' => 60,
        'strict_device' => 40,
        'resolve_ip' => 10,
        'internal' => 6000, // Next.js server (Cloudflare limits the shoppers in front of it)
        'slowdown_after' => 0.75, // fraction of a limit after which responses are delayed
        'slowdown_ms' => 400,
    ],

    'pagination' => ['max_page' => 20, 'max_per_page' => 48, 'max_ids' => 48],

    'bot' => [
        'distinct_products_per_minute' => 40,
        'bad_agents' => '/python-requests|python-urllib|aiohttp|httpx|scrapy|curl\/|wget|go-http-client|okhttp\/[0-3]\.|java\/|libwww|httpclient|node-fetch|axios\/|undici|headlesschrome|phantomjs|puppeteer|playwright|selenium|zgrab|masscan|nikto|sqlmap/i',
    ],

    'honeypot' => ['block_hours' => 24, 'product_id' => 'SR-HONEYPOT-0001'],

    'otp' => [
        'cooldown' => 60,          // seconds between codes for one phone
        'wrong_lockout' => 5,      // wrong codes (across codes) before the phone is locked
        'lockout_minutes' => 30,
        'ip_verify_per_10min' => 20,
    ],

    'admin' => [
        'require_2fa' => (bool) env('ADMIN_REQUIRE_2FA', ! $testing), // admin and manager roles
        'ip_allowlist' => env('ADMIN_IP_ALLOWLIST'), // comma separated IPs/CIDRs; empty = any (runtime overridable)
        'session_timeout_minutes' => (int) env('ADMIN_SESSION_TIMEOUT', 60),
        'password_min' => 12,
    ],

    'headers' => [
        'hsts' => (bool) env('SECURITY_HSTS', true),
    ],

    'activity' => ['retention_days' => 365],
];
