<?php

namespace App\Services\Catalog;

use App\Models\ProviderHealth;

/**
 * Per-provider circuit breaker stored in provider_health. Opens after N failures inside the window, skips the
 * provider for open_for seconds, then lets one half-open probe through: success closes it, failure re-opens it.
 * Keys are "provider:market" so a broken Flipkart parser does not take Amazon down.
 */
class CircuitBreaker
{
    public function allows(string $key): bool
    {
        $h = ProviderHealth::query()->find($key);
        if (! $h || $h->state === 'closed') {
            return true;
        }
        $c = config('retail.circuit');
        if ($h->state === 'open' && $h->opened_at && $h->opened_at->addSeconds((int) $c['open_for'])->isPast()) {
            // Half-open: claim the single probe atomically.
            return ProviderHealth::query()->whereKey($key)->where('state', 'open')->update(['state' => 'half_open', 'updated_at' => now()]) === 1;
        }

        return false;
    }

    public function success(string $key): void
    {
        ProviderHealth::query()->updateOrCreate(['provider' => $key], [
            'state' => 'closed', 'fail_count' => 0, 'opened_at' => null, 'last_success_at' => now(),
        ]);
    }

    public function failure(string $key): void
    {
        $c = config('retail.circuit');
        $h = ProviderHealth::query()->firstOrNew(['provider' => $key]);
        $inWindow = $h->last_failure_at && $h->last_failure_at->gt(now()->subSeconds((int) $c['window']));
        $h->fail_count = $inWindow ? $h->fail_count + 1 : 1;
        $h->last_failure_at = now();
        if ($h->state === 'half_open' || $h->fail_count >= (int) $c['failures']) {
            $h->state = 'open';
            $h->opened_at = now();
        }
        $h->save();
    }

    public function state(string $key): string
    {
        return ProviderHealth::query()->find($key)->state ?? 'closed';
    }
}
