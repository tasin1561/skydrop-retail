<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Admin;
use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class OrdersByMarketplaceChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Sales by store (this month)';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', now()->startOfMonth())
            ->selectRaw('order_items.market, count(distinct order_items.order_id) as orders, sum(order_items.unit_bdt * order_items.qty) as bdt')
            ->groupBy('order_items.market')->get()->keyBy('market');
        $markets = array_keys(Admin::MARKETS);

        return [
            'datasets' => [
                ['label' => 'Orders', 'data' => array_map(fn ($m) => (int) ($rows[$m]->orders ?? 0), $markets), 'backgroundColor' => '#5b3df5', 'yAxisID' => 'y'],
                ['label' => 'Sales (BDT)', 'data' => array_map(fn ($m) => round((float) ($rows[$m]->bdt ?? 0)), $markets), 'backgroundColor' => '#ff4d2e', 'yAxisID' => 'bdt'],
            ],
            'labels' => ['Amazon', 'Flipkart', 'Myntra'],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => [
            'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'title' => ['display' => true, 'text' => 'Orders']],
            'bdt' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'title' => ['display' => true, 'text' => 'BDT']],
        ]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
