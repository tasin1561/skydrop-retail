<?php

namespace App\Services\Catalog;

use App\Models\ApiLog;
use App\Models\Setting;
use App\Services\Alerts;
use App\Support\Limits;

/**
 * Monthly Oxylabs spend from api_logs. Above 100% of the budget, browsing goes to fallbacks/cache only and
 * Oxylabs is kept for checkout verification. Alerts fire once per month at 70, 90 and 100%.
 */
class BudgetGuard
{
    private const LEVELS = [100, 90, 70];

    public function __construct(private Alerts $alerts) {}

    public function budget(): float
    {
        return Limits::float('catalog.oxylabs_monthly_usd'); // reads through to the "oxylabs.monthly_budget_usd" setting
    }

    public function spent(): float
    {
        return (float) ApiLog::query()->where('provider', 'oxylabs')
            ->where('created_at', '>=', now()->startOfMonth())->sum('cost_usd');
    }

    public function ratio(): float
    {
        $b = $this->budget();

        return $b > 0 ? $this->spent() / $b : 0.0;
    }

    /** May a metered provider serve this purpose? Checkout verification is always allowed. */
    public function allowsMetered(string $purpose): bool
    {
        return $purpose === 'verify' || ($this->ratio() < 1.0 && ! $this->dailyCapHit());
    }

    /** Background ingestion (deals, bestsellers) stops earlier to leave room for shoppers. */
    public function allowsBackground(): bool
    {
        return $this->ratio() < 0.9 && ! $this->dailyCapHit();
    }

    // ---- System → Limits: daily Oxylabs cap and per-store daily live-fetch cap (same cache-only fallback as the monthly budget)

    public function dailyCap(): float
    {
        return Limits::float('catalog.oxylabs_daily_usd');
    }

    public function spentToday(): float
    {
        return (float) ApiLog::query()->where('provider', 'oxylabs')->where('created_at', '>=', now()->startOfDay())->sum('cost_usd');
    }

    public function dailyCapHit(): bool
    {
        $cap = $this->dailyCap();

        return $cap > 0 && $this->spentToday() >= $cap;
    }

    /** Provider calls (any provider, cache hits excluded) for one marketplace today. */
    public function storeCallsToday(string $market): int
    {
        return ApiLog::query()->where('market', $market)->where('provider', '!=', 'cache')->where('created_at', '>=', now()->startOfDay())->count();
    }

    /** Per-store daily live-fetch cap. Checkout verification is always allowed. */
    public function allowsStore(string $market, string $purpose): bool
    {
        $cap = Limits::int('catalog.store_daily_fetches');

        return $purpose === 'verify' || $cap <= 0 || $this->storeCallsToday($market) < $cap;
    }

    /** @return array{spent: float, budget: float, pct: int, alerted: ?int} */
    public function check(): array
    {
        $spent = $this->spent();
        $budget = $this->budget();
        $pct = $budget > 0 ? (int) floor($spent / $budget * 100) : 0;
        $key = 'budget_alert:'.now()->format('Y-m');
        $last = (int) Setting::get($key, 0);
        $alerted = null;
        foreach (self::LEVELS as $level) {
            if ($pct >= $level && $last < $level) {
                $this->alerts->send(sprintf('Oxylabs spend at %d%% of the monthly budget ($%.2f of $%.2f).%s', $pct, $spent, $budget,
                    $level >= 100 ? ' Browsing now uses fallbacks and cache; Oxylabs is reserved for checkout.' : ''), $level >= 100 ? 'error' : 'warning');
                Setting::put($key, $level);
                $alerted = $level;
                break;
            }
        }

        return ['spent' => round($spent, 4), 'budget' => $budget, 'pct' => $pct, 'alerted' => $alerted];
    }
}
