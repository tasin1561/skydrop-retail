<?php

// Finance module: profit report, accounting exports, fraud scoring. Every key can be overridden at runtime
// from Admin → System → Finance & security (settings table key "finance.<path>", see App\Support\Knobs).
return [
    // Gateway fee percent of the amount collected.
    'gateway_fees' => ['bkash' => 1.85, 'nagad' => 1.5, 'sslcommerz' => 2.5, 'card' => 2.5, 'mock' => 0, 'cod' => 1.0],

    // BDT paid per INR when we actually bought (default; a per-day rate in fx_paid_rates or orders.fx_paid wins).
    'fx_paid' => (float) env('FX_PAID_INR_BDT', 0), // 0 = use the order's fx_rate

    'usd_bdt' => (float) env('USD_BDT', 122), // converts Oxylabs/API spend (USD) to BDT

    // What Skydrop charges us for India → Bangladesh shipping.
    'skydrop_cost' => ['base_bdt' => 120, 'per_kg_bdt' => 330],

    'unboxing_cost_bdt' => 40, // our cost per unboxed order (when the order has unboxing)

    'vat_percent' => 0,         // placeholder: VAT on service margin
    'vat_on' => 'margin',       // margin | revenue

    'fraud' => [
        'medium_at' => 40,
        'high_at' => 70,
        'require_full_prepay_high' => true,
        'cache_days' => 7,
        'couriers' => [
            'steadfast' => [
                'enabled' => (bool) env('STEADFAST_FRAUD_ENABLED', true),
                'url' => env('STEADFAST_FRAUD_URL', 'https://portal.packzy.com/api/v1/fraud_check/{phone}'),
                'api_key' => env('STEADFAST_API_KEY'),
                'secret_key' => env('STEADFAST_SECRET_KEY'),
            ],
            'pathao' => [
                'enabled' => (bool) env('PATHAO_FRAUD_ENABLED', false),
                'base_url' => env('PATHAO_BASE_URL', 'https://api-hermes.pathao.com'),
                'client_id' => env('PATHAO_CLIENT_ID'), 'client_secret' => env('PATHAO_CLIENT_SECRET'),
                'username' => env('PATHAO_USERNAME'), 'password' => env('PATHAO_PASSWORD'),
            ],
            'redx' => [
                'enabled' => (bool) env('REDX_FRAUD_ENABLED', false),
                'base_url' => env('REDX_BASE_URL', 'https://openapi.redx.com.bd/v1.0.0-beta'),
                'token' => env('REDX_API_TOKEN'),
            ],
            'carrybee' => [
                'enabled' => (bool) env('CARRYBEE_FRAUD_ENABLED', false),
                'base_url' => env('CARRYBEE_BASE_URL'),
                'token' => env('CARRYBEE_API_TOKEN'),
            ],
        ],
        'disposable_domains' => ['mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'yopmail.com',
            'trashmail.com', 'sharklasers.com', 'getnada.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com', 'throwawaymail.com', 'mohmal.com'],
    ],
];
