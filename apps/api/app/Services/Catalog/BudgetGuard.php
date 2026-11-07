<?php

namespace App\Services\Catalog;

use App\Models\ApiLog;
use App\Models\Setting;
use App\Services\Alerts;
use App\Support\Limits;
use Illuminate\Support\Facades\Cache;

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

    /**
     * May a metered provider serve this purpose? Checkout price checks have their own daily ceiling
     * (System → Limits "Oxylabs price checks per day"); past it they fall back to recent prices (verify grace).
     */
    public function allowsMetered(string $purpose): bool
    {
        if ($purpose === 'verify') {
            return ! $this->verifyCapHit();
        }

        return $this->ratio() < 1.0 && ! $this->dailyCapHit();
    }

    // ---- Daily ceiling for checkout price checks (approximate USD, counted per successful metered call)

    private function verifyKey(): string
    {
        return 'oxylabs:verify-spend:'.now()->toDateString();
    }

    public function verifyCap(): float
    {
        return Limits::float('catalog.verify_daily_usd');
    }

    public function verifySpentToday(): float
    {
        return ((int) Cache::get($this->verifyKey(), 0)) / 1_000_000; // stored in micro-dollars (integer increments are atomic)
    }

    public function verifyCapHit(): bool
    {
        $cap = $this->verifyCap();

        return $cap > 0 && $this->verifySpentToday() >= $cap;
    }

    public function recordVerifySpend(float $usd): void
    {
        $key = $this->verifyKey();
        Cache::add($key, 0, now()->addDays(2));
        Cache::increment($key, (int) round($usd * 1_000_000));
        if ($this->verifyCapHit() && Cache::add($key.':alerted', 1, now()->addDay())) {
            rescue(fn () => $this->alerts->send(sprintf('Oxylabs price-check ceiling reached: $%.2f today (ceiling $%.2f). Orders now use prices checked in the last 72 hours until midnight. Change it in System → Limits.',
                $this->verifySpentToday(), $this->verifyCap()), 'warning'), null, false);
        }
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
