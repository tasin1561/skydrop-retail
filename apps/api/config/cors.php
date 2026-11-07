<?php

// CORS for the storefront (WEB_URL) and optional extra origins (CORS_EXTRA_ORIGINS, comma separated).
// Bearer tokens (no cookies), so credentials are not needed.
$origins = array_values(array_filter(array_map('trim', array_merge(
    [rtrim((string) env('WEB_URL', 'http://localhost:3000'), '/')],
    explode(',', (string) env('CORS_EXTRA_ORIGINS', '')),
))));

return [
    'paths' => ['api/v1/*'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
