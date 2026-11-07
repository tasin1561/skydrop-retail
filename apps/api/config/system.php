<?php

// System module: limits, health, alerts and maintenance. See docs/OPERATIONS.md.
// Limit values live in App\Support\Limits (Admin → System → Limits); this file holds the ops plumbing only.
return [
    // Shared secret for GET /api/v1/health/details (header X-Health-Token). Empty = endpoint disabled (404).
    'health_token' => env('HEALTH_TOKEN'),

    // Seconds after which a heartbeat counts as stale (worker / scheduler cards go red, /health/ready answers 503).
    'heartbeat_stale_after' => (int) env('HEARTBEAT_STALE_AFTER', 180),

    // Optional deploy timestamp / version (CI can set these); otherwise read from bootstrap/cache and .git.
    'deployed_at' => env('APP_DEPLOYED_AT'),
    'version' => env('APP_VERSION'),

    // retail:health-check thresholds (red transitions alert once, recovery alerts once).
    'alerts' => [
        'failed_jobs_per_hour' => (int) env('HEALTH_FAILED_JOBS_SPIKE', 20),
        'circuit_open_minutes' => 15,
        'disk_free_pct' => 10,
    ],

    // Days to keep storage/logs/*.log files (Admin → System → Settings overrides via settings key "system.log_retention_days").
    'log_retention_days' => (int) env('LOG_RETENTION_DAYS', 14),
];
