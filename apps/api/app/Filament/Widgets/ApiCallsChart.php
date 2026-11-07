<?php

namespace App\Filament\Widgets;

use App\Models\ApiLog;
use App\Models\Product;
use App\Models\SearchCache;
use Filament\Widgets\ChartWidget;

/**
 * Paid provider calls per day, plus the cache hit rate. The catalog module does not log cache hits to api_logs,
 * so hits are estimated as page/search views served without a provider call: product views and cached searches
 * refreshed in the window vs paid calls. When the catalog logs `type = cache_hit` rows (status 304, cost 0) they are used instead.
 */
class ApiCallsChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Provider calls per day';

    protected ?string $pollingInterval = '300s';

    public ?string $filter = '14';

    protected function getFilters(): ?array
    {
        return ['7' => 'Last 7 days', '14' => 'Last 14 days', '30' => 'Last 30 days'];
    }

    public function getDescription(): ?string
    {
        [$hits, $calls] = self::hitStats((int) $this->filter);
        $total = $hits + $calls;

        return $total ? 'Cache hit rate '.round($hits / $total * 100, 1).'% ('.number_format($hits).' served from cache, '.number_format($calls).' paid calls)' : 'No traffic yet';
    }

    /** @return array{0: int, 1: int} [cache hits, paid calls] over the last N days */
    public static function hitStats(int $days): array
    {
        $since = now()->subDays($days);
        $calls = ApiLog::query()->where('created_at', '>=', $since)->where('cost_usd', '>', 0)->count();
        $hits = ApiLog::query()->where('created_at', '>=', $since)->whereIn('type', ['cache_hit', 'cache'])->count();
        if ($hits === 0) {
            // Estimate: searches answered from search_cache are not logged; fall back to logged zero-cost calls.
            $hits = ApiLog::query()->where('created_at', '>=', $since)->where('cost_usd', 0)->whereBetween('status', [200, 399])->count();
        }

        return [$hits, $calls];
    }

    protected function getData(): array
    {
        $days = (int) $this->filter;
        $labels = [];
        $ok = [];
        $failed = [];
        $cost = [];
        for ($d = $days - 1; $d >= 0; $d--) {
            $from = now()->subDays($d)->startOfDay();
            $to = now()->subDays($d)->endOfDay();
            $q = ApiLog::query()->whereBetween('created_at', [$from, $to])->whereNotIn('type', ['cache_hit', 'cache']);
            $labels[] = $from->format('d M');
            $ok[] = (clone $q)->whereBetween('status', [200, 399])->count();
            $failed[] = (clone $q)->where(fn ($q) => $q->where('status', '>=', 400)->orWhere('status', 0))->count();
            $cost[] = round((float) (clone $q)->sum('cost_usd'), 3);
        }

        return [
            'datasets' => [
                ['label' => 'Successful', 'data' => $ok, 'backgroundColor' => '#0b6bcb', 'stack' => 'calls'],
                ['label' => 'Failed', 'data' => $failed, 'backgroundColor' => '#ff6a3d', 'stack' => 'calls'],
                ['label' => 'Cost (USD)', 'data' => $cost, 'type' => 'line', 'borderColor' => '#64748b', 'backgroundColor' => '#64748b', 'yAxisID' => 'cost', 'tension' => 0.3, 'pointRadius' => 2],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Calls']],
                'cost' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'title' => ['display' => true, 'text' => 'USD']],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
