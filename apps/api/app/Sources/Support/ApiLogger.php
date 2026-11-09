<?php

namespace App\Sources\Support;

use App\Models\ApiLog;
use Throwable;

/**
 * Writes one api_logs row per provider call (and cache hits, cost 0) for the cost dashboard, the budget guard and the
 * "Product data usage" page. Every live row also records its purpose (UsageContext), whether it succeeded (a 2xx answer
 * with content; failures are never billable) and whether it was a retry (fallback provider, hedge duplicate, job retry).
 */
class ApiLogger
{
    public function log(string $provider, string $type, ?string $market, ?string $key, int $status, int $ms,
        bool $rendered = false, float $cost = 0.0, ?string $error = null, ?bool $retry = null): void
    {
        $live = $provider !== 'cache';
        try {
            ApiLog::query()->create([
                'provider' => $provider, 'type' => $type, 'market' => $market, 'key' => $key ? mb_substr($key, 0, 250) : null,
                'status' => $status, 'ms' => $ms, 'rendered' => $rendered, 'cost_usd' => round($cost, 6),
                'error' => $error ? mb_substr($error, 0, 500) : null,
                'purpose' => $live ? (UsageContext::purpose() ?? UsageContext::fromType($type)) : null,
                'success' => $live && self::succeeded($status, $error),
                'is_retry' => $live && ($retry ?? UsageContext::retry()),
            ]);
        } catch (Throwable) {
            // Logging must never break a fetch.
        }
    }

    /** A billable retrieval: the store page came back (2xx) with no error noted. */
    public static function succeeded(int $status, ?string $error): bool
    {
        return $status >= 200 && $status < 300 && ($error === null || $error === '');
    }

    public function cacheHit(string $type, ?string $market, ?string $key): void
    {
        $this->log('cache', 'cache_hit', $market, $key, 200, 0);
    }

    /** USD cost of one successful result: config rates are per 1,000 results. */
    public static function oxylabsCost(string $market, bool $rendered): float
    {
        $rates = (array) config('retail.oxylabs.rates');
        $rate = $market === 'amazon' ? ($rates['amazon'] ?? 0.45) : ($rendered ? ($rates['other_js'] ?? 1.40) : ($rates['other'] ?? 0.90));

        return $rate / 1000;
    }
}
