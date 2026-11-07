<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Admin;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrdersByStatusChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Orders by status';

    protected ?string $description = 'All open and recent orders (last 90 days)';

    protected ?string $maxHeight = '280px';

    private const HEX = ['gray' => '#a1a1aa', 'info' => '#0ea5e9', 'primary' => '#5b3df5', 'warning' => '#f59e0b', 'success' => '#10b981', 'danger' => '#e5363d'];

    protected function getData(): array
    {
        $counts = Order::query()->goods()->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $statuses = array_values(array_filter(Order::STATUSES, fn ($s) => ($counts[$s] ?? 0) > 0)) ?: Order::STATUSES;

        return [
            'datasets' => [[
                'label' => 'Orders',
                'data' => array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses),
                'backgroundColor' => array_map(fn ($s) => self::HEX[Admin::ORDER_STATUS_COLORS[$s]] ?? '#94a3b8', $statuses),
            ]],
            'labels' => array_map(fn ($s) => Admin::ORDER_STATUS_LABELS[$s], $statuses),
        ];
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
