<?php

// Skydrop Retail settings. Values marked "admin" can be overridden at runtime from the admin panel (settings table).
return [
    'pincode' => env('RETAIL_PINCODE', '700001'), // Kolkata receiving pincode for "Deliver to"

    'oxylabs' => [
        'base_url' => env('OXYLABS_URL', 'https://realtime.oxylabs.io/v1/queries'),
        'token' => env('OXYLABS_TOKEN'),          // Bearer token (Web Scraper API)
        'username' => env('OXYLABS_USERNAME'),    // or basic auth
        'password' => env('OXYLABS_PASSWORD'),
        'timeout' => (int) env('OXYLABS_TIMEOUT', 25),
        'hedge_after' => (int) env('OXYLABS_HEDGE_AFTER', 8), // seconds; 0 = off (admin)
        // USD per 1,000 results (Basic plan)
        'rates' => ['amazon' => 0.45, 'other' => 0.90, 'other_js' => 1.40],
        'monthly_budget_usd' => (float) env('OXYLABS_BUDGET_USD', 100), // (admin)
    ],

    'fallbacks' => [
        'omkar' => ['key' => env('OMKAR_API_KEY'), 'url' => env('OMKAR_API_URL', 'https://amazon-scraper-api.omkar.cloud/amazon')],
        'openwebninja' => ['key' => env('RAPIDAPI_KEY'), 'host' => 'real-time-amazon-data.p.rapidapi.com'],
    ],

    'circuit' => ['failures' => 5, 'window' => 120, 'open_for' => 600],

    // Freshness in minutes (admin)
    'ttl' => [
        'search_fresh' => 360, 'search_stale' => 10080,
        'product_fresh' => 1440, 'product_stale' => 10080,
        'sibling_fresh' => 4320,
        'details_fresh' => 10080,
        'raw_html_days' => 3,
        'lightning' => 30,       // products whose deal ends within 24 h
        'api_logs_days' => 90,
    ],

    // Offer / deal ingestion (catalog module)
    'offers' => [
        'pass_coupons' => true,  // (admin) price with deal price minus clippable coupon when we can use it
        'ingest_every_hours' => 6,
        'ingest_max_products' => (int) env('DEALS_MAX_PRODUCTS', 40), // detail fetches per deals run per market
        'deal_urls' => [
            'amazon' => 'https://www.amazon.in/deals',
            'flipkart' => 'https://www.flipkart.com/offers-store',
            'bigbasket' => 'https://www.bigbasket.com/offers/',
        ],
    ],

    // Which providers serve each marketplace, in order (catalog ProviderChain). Fallbacks are Amazon only.
    'sources' => [
        'amazon' => [App\Sources\OxylabsAmazonSource::class, App\Sources\OmkarAmazonSource::class, App\Sources\OpenWebNinjaAmazonSource::class],
        'flipkart' => [App\Sources\OxylabsFlipkartSource::class],
        'bigbasket' => [App\Sources\OxylabsBigBasketSource::class],
    ],

    'fx_feed_url' => env('FX_FEED_URL'), // optional JSON endpoint returning {"rate": <BDT per INR>}

    'variants' => ['mode' => env('VARIANT_MODE', 'eager'), 'max' => (int) env('VARIANT_MAX', 60)],
    'prefetch_top' => (int) env('PREFETCH_TOP', 3),

    'pricing' => [
        'fx_inr_bdt' => (float) env('FX_INR_BDT', 1.42),
        'fx_buffer' => 0.015,
        'shipping' => ['base_bdt' => 180, 'per_kg_bdt' => 420, 'free_above_bdt' => 15000],
    ],

    'skydrop' => [
        'base_url' => env('SKYDROP_API_URL', 'https://app.skydrop.online/api'),
        'token' => env('SKYDROP_API_TOKEN'),
        'webhook_secret' => env('SKYDROP_WEBHOOK_SECRET'),
        'mode' => env('SKYDROP_MODE', 'mock'), // mock | live
        'hub' => 'Kolkata',
    ],

    'payments' => [
        'mode' => env('PAYMENTS_MODE', 'mock'), // mock | sandbox | live
        'bkash' => ['app_key' => env('BKASH_APP_KEY'), 'app_secret' => env('BKASH_APP_SECRET'), 'username' => env('BKASH_USERNAME'), 'password' => env('BKASH_PASSWORD'),
            'sandbox_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta', 'live_url' => env('BKASH_LIVE_URL', 'https://tokenized.pay.bka.sh/v1.2.0-beta')],
        'nagad' => ['merchant_id' => env('NAGAD_MERCHANT_ID'), 'public_key' => env('NAGAD_PUBLIC_KEY'), 'private_key' => env('NAGAD_PRIVATE_KEY'),
            'merchant_number' => env('NAGAD_MERCHANT_NUMBER'),
            'sandbox_url' => 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0', 'live_url' => env('NAGAD_LIVE_URL', 'https://api.mynagad.com')],
        'sslcommerz' => ['store_id' => env('SSLC_STORE_ID'), 'store_password' => env('SSLC_STORE_PASSWORD'),
            'sandbox_url' => 'https://sandbox.sslcommerz.com', 'live_url' => 'https://securepay.sslcommerz.com'],
    ],

    'otp' => ['driver' => env('OTP_DRIVER', 'log'), 'ttl' => 300, 'max_attempts' => 5],

    // ---- Commerce module ----
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'), // log | http
        'http' => [
            // Generic BD gateway (SSL Wireless ISMS / BulkSMSBD style). Fields are mapped by name.
            'url' => env('SMS_HTTP_URL'),             // e.g. https://smsplus.sslwireless.com/api/v3/send-sms
            'api_token' => env('SMS_HTTP_TOKEN'),
            'sender_id' => env('SMS_HTTP_SENDER_ID'),  // SID / senderid
            'style' => env('SMS_HTTP_STYLE', 'sslwireless'), // sslwireless | bulksmsbd
            'timeout' => 10,
        ],
    ],

    'checkout' => [
        'price_tolerance' => 0.03, // re-verify: >3% change => 409
        'return_window_days' => 3,
        'advance_ratio' => 0.5,
    ],

    // Loyalty points (admin can override via settings key "points" with the same shape)
    'points' => [
        'enabled' => true,
        'earn_per_bdt' => 100,      // 1 point per ৳100 of item subtotal (after coupon)
        'expire_months' => 12,
        'redeem_block' => 100,      // redeem in blocks of 100 points
        'redeem_block_bdt' => 50,   // = ৳50 off items
        'redeem_max_ratio' => 0.20, // max 20% of item subtotal
        'signup_bonus' => (int) env('POINTS_SIGNUP_BONUS', 100),
    ],

    'skydrop_webhook_tolerance' => 300, // seconds, when X-Skydrop-Timestamp is sent
    'admin_password' => env('ADMIN_PASSWORD', 'change-me-now'),

    'alerts' => [
        'telegram_bot' => env('ALERT_TELEGRAM_BOT'), 'telegram_chat' => env('ALERT_TELEGRAM_CHAT'),
        'email' => env('ALERT_EMAIL'),
    ],

    'web_url' => env('WEB_URL', 'http://localhost:3000'),
];
