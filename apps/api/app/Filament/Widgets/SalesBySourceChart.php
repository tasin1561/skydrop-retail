<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Services\Tracking\Attribution;
use Filament\Widgets\ChartWidget;

/** Revenue and orders by utm_source / campaign (orders.attribution), last 30 days, cancelled orders excluded. */
class SalesBySourceChart extends ChartWidget
{
    protected static ?int $sort = 7;

    protected ?string $heading = 'Sales by source (last 30 days)';

    protected ?string $maxHeight = '300px';

    /** @return array<string, array{orders: int, bdt: float}> top sources by revenue */
    public static function rows(int $limit = 8): array
    {
        $rows = [];
        $q = Order::query()->where('status', '!=', 'cancelled')->where('created_at', '>=', now()->subDays(30))
            ->select(['id', 'attribution', 'total_bdt']);
        foreach ($q->cursor() as $o) {
            $s = Attribution::source($o->attribution);
            $label = $s['source'].($s['campaign'] ? ' / '.$s['campaign'] : '');
            $rows[$label] ??= ['orders' => 0, 'bdt' => 0.0];
            $rows[$label]['orders']++;
            $rows[$label]['bdt'] += (float) $o->total_bdt;
        }
        uasort($rows, fn ($a, $b) => $b['bdt'] <=> $a['bdt']);

        return array_slice($rows, 0, $limit, true);
    }

    protected function getData(): array
    {
        $rows = self::rows();

        return [
            'datasets' => [
                ['label' => 'Sales (BDT)', 'data' => array_map(fn ($r) => round($r['bdt']), array_values($rows)), 'backgroundColor' => '#ff4d2e', 'xAxisID' => 'x'],
                ['label' => 'Orders', 'data' => array_map(fn ($r) => $r['orders'], array_values($rows)), 'backgroundColor' => '#5b3df5', 'xAxisID' => 'orders'],
            ],
            'labels' => array_map(fn ($l) => mb_strimwidth($l, 0, 40, '…'), array_keys($rows)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'BDT']],
                'orders' => ['position' => 'top', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'ticks' => ['precision' => 0]],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
