<?php

namespace App\Services\Catalog;

use App\Models\ApiLog;
use App\Models\Setting;
use App\Services\Alerts;
use App\Sources\Support\UsageContext;
use App\Support\BizTime;
use App\Support\Limits;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Product data usage against the monthly operating plan (System → Product data usage).
 *
 * The plan is ONE shared pool: data.capacity_amazon_calls Amazon India calls OR data.capacity_flipkart_calls Flipkart
 * calls, or a proportional mix. Usage is measured in Amazon-call units: 1 Flipkart call = amazon ÷ flipkart units
 * (220,000 ÷ 68,000 ≈ 3.235 by default). Only successful live retrieval calls count; failed calls and cache hits never
 * do. Retries are counted separately (a retry that succeeded is still one billable call). Other stores are shown but
 * are outside the plan. Billing cycles run from data.cycle_start_day to the same day next month, in Bangladesh time.
 */
class DataUsage
{
    public const PLAN_MARKETS = ['amazon', 'flipkart'];

    public function __construct(private Alerts $alerts) {}

    // ---------------------------------------------------------------- plan

    public function amazonCapacity(): int
    {
        return Limits::int('data.capacity_amazon_calls');
    }

    public function flipkartCapacity(): int
    {
        return Limits::int('data.capacity_flipkart_calls');
    }

    public function alertPercent(): int
    {
        return Limits::int('data.capacity_alert_percent');
    }

    /** Amazon-call units one Flipkart call uses (0 when the plan has no Flipkart capacity). */
    public function flipkartWeight(): float
    {
        $f = $this->flipkartCapacity();

        return $f > 0 ? $this->amazonCapacity() / $f : 0.0;
    }

    // ---------------------------------------------------------------- cycles

    /**
     * The billing cycle containing $at (default now). start inclusive, end exclusive, both UTC instants.
     *
     * @return array{start: Carbon, end: Carbon, key: string, label: string}
     */
    public function cycle(?CarbonInterface $at = null): array
    {
        $local = Carbon::instance($at ?? now())->setTimezone(BizTime::TZ);
        $day = $this->startDay();
        $start = $local->copy()->startOfMonth()->setDay($day)->startOfDay();
        if ($local->lt($start)) {
            $start = $start->subMonthNoOverflow()->setDay($day);
        }

        return $this->build($start);
    }

    /**
     * The cycle that starts in month "Y-m".
     *
     * @return array{start: Carbon, end: Carbon, key: string, label: string}
     */
    public function cycleFor(string $ym): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return $this->cycle();
        }

        return $this->build(Carbon::createFromFormat('Y-m-d', $ym.'-01', BizTime::TZ)->setDay($this->startDay())->startOfDay());
    }

    /** @return array<string, string> "Y-m" => label, current cycle first */
    public function cycleOptions(int $count = 12): array
    {
        $out = [];
        $c = $this->cycle();
        for ($i = 0; $i < $count; $i++) {
            $out[$c['key']] = $c['label'].($i === 0 ? ' (current)' : '');
            $c = $this->build($c['start']->copy()->setTimezone(BizTime::TZ)->subMonthNoOverflow()->setDay($this->startDay()));
        }

        return $out;
    }

    private function startDay(): int
    {
        return max(1, min(28, Limits::int('data.cycle_start_day')));
    }

    /** @return array{start: Carbon, end: Carbon, key: string, label: string} */
    private function build(Carbon $localStart): array
    {
        $localEnd = $localStart->copy()->addMonthNoOverflow();
        $lastDay = $localEnd->copy()->subDay();

        return [
            'start' => $localStart->copy()->utc(),
            'end' => $localEnd->copy()->utc(),
            'key' => $localStart->format('Y-m'),
            'label' => $localStart->format('j M Y').' – '.$lastDay->format('j M Y'),
        ];
    }

    // ---------------------------------------------------------------- numbers

    /**
     * Aggregated live calls in [start, end): one row per Dhaka day × market × purpose × success × retry.
     *
     * @return list<array{day: string, market: string, purpose: string, success: bool, retry: bool, n: int}>
     */
    public function rows(Carbon $start, Carbon $end): array
    {
        $day = $this->dayExpression();

        return ApiLog::query()
            ->where('provider', '!=', 'cache')->where('type', '!=', 'cache_hit')
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw("$day as d, coalesce(market, 'other') as m, coalesce(purpose, 'other') as p, success as ok, is_retry as r, count(*) as n")
            ->groupByRaw("$day, coalesce(market, 'other'), coalesce(purpose, 'other'), success, is_retry")
            ->toBase()->get()
            ->map(fn ($r) => ['day' => (string) $r->d, 'market' => (string) $r->m, 'purpose' => (string) $r->p,
                'success' => (bool) $r->ok, 'retry' => (bool) $r->r, 'n' => (int) $r->n])
            ->all();
    }

    /**
     * Everything the page needs for one cycle.
     *
     * @param  array{start: Carbon, end: Carbon, key: string, label: string}|null  $cycle
     * @return array<string, mixed>
     */
    public function report(?array $cycle = null, ?CarbonInterface $now = null): array
    {
        $cycle ??= $this->cycle($now);
        $now = Carbon::instance($now ?? now());
        $rows = $this->rows($cycle['start'], $cycle['end']);

        $ok = ['amazon' => 0, 'flipkart' => 0, 'other' => 0];
        $failed = ['amazon' => 0, 'flipkart' => 0, 'other' => 0];
        $retries = 0;
        $retriesOk = 0;
        foreach ($rows as $r) {
            $m = in_array($r['market'], self::PLAN_MARKETS, true) ? $r['market'] : 'other';
            $r['success'] ? $ok[$m] += $r['n'] : $failed[$m] += $r['n'];
            if ($r['retry']) {
                $retries += $r['n'];
                $retriesOk += $r['success'] ? $r['n'] : 0;
            }
        }

        $capA = $this->amazonCapacity();
        $weight = $this->flipkartWeight();
        $used = $this->units($ok['amazon'], $ok['flipkart']);
        $pct = $capA > 0 ? $used / $capA * 100 : 0.0;
        $remaining = max(0.0, $capA - $used);

        // Projection at the average daily rate so far (the full cycle when it is over).
        $cycleSeconds = $cycle['start']->diffInSeconds($cycle['end']);
        $elapsed = max(0, min($cycleSeconds, $cycle['start']->diffInSeconds($now, false)));
        $elapsedDays = $elapsed / 86400;
        $over = $now->gte($cycle['end']);
        $perDay = $elapsedDays > 0 ? $used / max(1, $elapsedDays) : 0.0;
        $projected = $over ? $used : ($elapsedDays > 0 ? $used / max(1, $elapsedDays) * ($cycleSeconds / 86400) : 0.0);

        return [
            'cycle' => $cycle,
            'over' => $over,
            'started' => $elapsed > 0,
            'capacity' => ['amazon' => $capA, 'flipkart' => $this->flipkartCapacity(), 'weight' => $weight, 'alert' => $this->alertPercent()],
            'ok' => $ok,
            'failed' => $failed,
            'failed_total' => array_sum($failed),
            'retries' => $retries,
            'retries_ok' => $retriesOk,
            'used_units' => $used,
            'pct' => $pct,
            'remaining_units' => $remaining,
            'remaining_amazon' => (int) floor($remaining),
            'remaining_flipkart' => $weight > 0 ? (int) floor($remaining / $weight) : 0,
            'per_day_units' => $perDay,
            'projected_units' => $projected,
            'projected_pct' => $capA > 0 ? $projected / $capA * 100 : 0.0,
            'days' => $this->byDay($rows, $cycle),
            'purposes' => $this->byPurpose($rows),
        ];
    }

    /** Pool units used by successful calls. */
    public function units(int $amazonOk, int $flipkartOk): float
    {
        return $amazonOk + $flipkartOk * $this->flipkartWeight();
    }

    /**
     * @param  list<array{day: string, market: string, purpose: string, success: bool, retry: bool, n: int}>  $rows
     * @param  array{start: Carbon, end: Carbon}  $cycle
     * @return array<string, array{amazon: int, flipkart: int, other: int, failed: int, retries: int, units: float}>
     */
    private function byDay(array $rows, array $cycle): array
    {
        $out = [];
        $d = $cycle['start']->copy()->setTimezone(BizTime::TZ);
        $end = $cycle['end']->copy()->setTimezone(BizTime::TZ);
        while ($d->lt($end)) {
            $out[$d->toDateString()] = ['amazon' => 0, 'flipkart' => 0, 'other' => 0, 'failed' => 0, 'retries' => 0, 'units' => 0.0];
            $d->addDay();
        }
        foreach ($rows as $r) {
            if (! isset($out[$r['day']])) {
                continue;
            }
            $m = in_array($r['market'], self::PLAN_MARKETS, true) ? $r['market'] : 'other';
            if ($r['success']) {
                $out[$r['day']][$m] += $r['n'];
            } else {
                $out[$r['day']]['failed'] += $r['n'];
            }
            $out[$r['day']]['retries'] += $r['retry'] ? $r['n'] : 0;
        }
        foreach ($out as $day => $v) {
            $out[$day]['units'] = $this->units($v['amazon'], $v['flipkart']);
        }

        return $out;
    }

    /**
     * @param  list<array{day: string, market: string, purpose: string, success: bool, retry: bool, n: int}>  $rows
     * @return array<string, array{label: string, amazon: int, flipkart: int, other: int, failed: int, retries: int, units: float}>
     */
    private function byPurpose(array $rows): array
    {
        $out = [];
        foreach ([...array_keys(UsageContext::PURPOSES), 'other'] as $p) {
            $out[$p] = ['label' => UsageContext::label($p), 'amazon' => 0, 'flipkart' => 0, 'other' => 0, 'failed' => 0, 'retries' => 0, 'units' => 0.0];
        }
        foreach ($rows as $r) {
            $p = isset($out[$r['purpose']]) ? $r['purpose'] : 'other';
            $m = in_array($r['market'], self::PLAN_MARKETS, true) ? $r['market'] : 'other';
            if ($r['success']) {
                $out[$p][$m] += $r['n'];
            } else {
                $out[$p]['failed'] += $r['n'];
            }
            $out[$p]['retries'] += $r['retry'] ? $r['n'] : 0;
        }
        foreach ($out as $p => $v) {
            $out[$p]['units'] = $this->units($v['amazon'], $v['flipkart']);
        }

        return array_filter($out, fn ($v) => $v['amazon'] + $v['flipkart'] + $v['other'] + $v['failed'] > 0);
    }

    /**
     * CSV rows for one cycle: one line per day × purpose × store with any calls.
     *
     * @param  array{start: Carbon, end: Carbon, key: string, label: string}  $cycle
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    public function csv(array $cycle): array
    {
        $agg = [];
        foreach ($this->rows($cycle['start'], $cycle['end']) as $r) {
            $k = $r['day'].'|'.$r['purpose'].'|'.$r['market'];
            $agg[$k] ??= ['day' => $r['day'], 'purpose' => $r['purpose'], 'market' => $r['market'], 'ok' => 0, 'failed' => 0, 'retries' => 0];
            $agg[$k][$r['success'] ? 'ok' : 'failed'] += $r['n'];
            $agg[$k]['retries'] += $r['retry'] ? $r['n'] : 0;
        }
        ksort($agg);
        $weight = $this->flipkartWeight();
        $rows = [];
        foreach ($agg as $a) {
            $units = match ($a['market']) {
                'amazon' => (float) $a['ok'],
                'flipkart' => $a['ok'] * $weight,
                default => 0.0,
            };
            $rows[] = [$a['day'], UsageContext::label($a['purpose']), self::marketLabel($a['market']), $a['ok'], $a['failed'], $a['retries'], round($units, 2)];
        }

        return [['Date', 'Purpose', 'Store', 'Successful calls (billable)', 'Failed calls (not billed)', 'Retries', 'Plan units used (Amazon-call units)'], $rows];
    }

    public static function marketLabel(string $market): string
    {
        return match ($market) {
            'amazon' => 'Amazon India',
            'flipkart' => 'Flipkart',
            'bigbasket' => 'BigBasket (outside the plan)',
            default => 'Other (outside the plan)',
        };
    }

    // ---------------------------------------------------------------- alerts

    /** Thresholds that alert once per cycle: the alert percent and 100%. @return list<int> */
    public function thresholds(): array
    {
        $levels = array_values(array_unique([min(100, max(1, $this->alertPercent())), 100]));
        sort($levels);

        return $levels;
    }

    /**
     * Sends at most one alert per threshold per cycle (the highest newly crossed one). Returns the level alerted, if any.
     */
    public function checkAlerts(?CarbonInterface $now = null): ?int
    {
        if ($this->amazonCapacity() <= 0) {
            return null;
        }
        $r = $this->report(null, $now);
        $key = 'data_usage_alert:'.$r['cycle']['key'];
        $last = (int) Setting::get($key, 0);
        $hit = null;
        foreach ($this->thresholds() as $level) {
            if ($r['pct'] >= $level && $last < $level) {
                $hit = $level;
            }
        }
        if ($hit === null) {
            return null;
        }
        Setting::put($key, $hit);
        $this->alerts->send(sprintf(
            'Product data usage is at %s%% of this cycle\'s plan (%s). %s',
            number_format($r['pct'], 1), $r['cycle']['label'],
            $hit >= 100
                ? 'The monthly plan is used up; further successful calls are over the plan.'
                : sprintf('About %s Amazon calls or %s Flipkart calls are left.', number_format($r['remaining_amazon']), number_format($r['remaining_flipkart'])),
        ), $hit >= 100 ? 'error' : 'warning');

        return $hit;
    }

    /** Highest threshold already alerted in the cycle (0 = none). */
    public function alertedLevel(string $cycleKey): int
    {
        return (int) Setting::get('data_usage_alert:'.$cycleKey, 0);
    }

    // ---------------------------------------------------------------- helpers

    /** SQL for the Bangladesh (UTC+6, no DST) calendar date of created_at. */
    private function dayExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'DATE(DATE_ADD(created_at, INTERVAL 6 HOUR))',
            'pgsql' => "DATE(created_at + INTERVAL '6 hours')",
            'sqlsrv' => 'CAST(DATEADD(hour, 6, created_at) AS date)',
            default => "date(created_at, '+6 hours')",
        };
    }
}
