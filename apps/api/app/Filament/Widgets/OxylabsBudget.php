<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Settings;
use App\Filament\Support\Admin;
use App\Models\ApiLog;
use App\Models\Marketplace;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Provider spend this month vs the monthly budget (Settings → monthly budget), with a straight-line month-end projection. */
class OxylabsBudget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Data provider spend (Oxylabs and fallbacks)';

    protected ?string $pollingInterval = '120s';

    /** @return array{spent: float, budget: float, pct: float, projected: float, projected_pct: float, color: string} */
    public static function figures(): array
    {
        $start = now()->startOfMonth();
        $spent = (float) ApiLog::query()->where('created_at', '>=', $start)->sum('cost_usd');
        $budget = (float) Admin::setting('oxylabs.monthly_budget_usd', 100);
        $elapsed = max(1 / 24, now()->diffInSeconds($start, true) / 86400);
        $projected = $spent / $elapsed * now()->daysInMonth;
        $pct = $budget > 0 ? $spent / $budget * 100 : 0;

        return [
            'spent' => $spent, 'budget' => $budget, 'pct' => $pct, 'projected' => $projected,
            'projected_pct' => $budget > 0 ? $projected / $budget * 100 : 0, 'color' => self::color($pct),
        ];
    }

    public static function color(float $pct): string
    {
        return match (true) {
            $pct >= 100 => 'danger',
            $pct >= 90 => 'danger',
            $pct >= 70 => 'warning',
            default => 'success',
        };
    }

    protected function getStats(): array
    {
        $f = self::figures();
        $daily = collect(range(13, 0))->map(fn ($d) => round((float) ApiLog::query()
            ->whereBetween('created_at', [now()->subDays($d)->startOfDay(), now()->subDays($d)->endOfDay()])->sum('cost_usd'), 4))->all();
        $level = match (true) {
            $f['pct'] >= 100 => 'Budget exceeded',
            $f['pct'] >= 90 => 'Over 90% of budget',
            $f['pct'] >= 70 => 'Over 70% of budget',
            default => 'Within budget',
        };
        $byMarket = ApiLog::query()->where('created_at', '>=', now()->startOfMonth())->whereNotNull('market')
            ->selectRaw('market, sum(cost_usd) as c')->groupBy('market')->pluck('c', 'market');
        $shares = Marketplace::query()->pluck('budget_share', 'id');
        $marketLine = $byMarket->map(fn ($c, $m) => ucfirst($m).' $'.number_format($c, 2).(isset($shares[$m]) && $f['budget'] > 0 ? ' / $'.number_format($f['budget'] * $shares[$m] / 100, 0) : ''))->implode(' · ');

        return [
            Stat::make('Spent this month', '$'.number_format($f['spent'], 2))
                ->description(round($f['pct'], 1).'% of $'.number_format($f['budget'], 0).' · '.$level)
                ->descriptionIcon($f['pct'] >= 70 ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
                ->color($f['color'])->chart($daily)
                ->url(Settings::canAccess() ? Settings::getUrl() : null),
            Stat::make('Projected month-end', '$'.number_format($f['projected'], 2))
                ->description(round($f['projected_pct']).'% of budget at the current pace')
                ->color(self::color($f['projected_pct']))->icon(Heroicon::OutlinedArrowTrendingUp),
            Stat::make('Spend by store', $byMarket->isEmpty() ? '—' : '$'.number_format($byMarket->sum(), 2))
                ->description($marketLine ?: 'No store-tagged calls yet')->icon(Heroicon::OutlinedBuildingStorefront),
        ];
    }
}
