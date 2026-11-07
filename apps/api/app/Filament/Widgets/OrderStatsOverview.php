<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Models\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $tz = 'Asia/Dhaka';
        $today = now($tz)->startOfDay()->utc();
        $month = now($tz)->startOfMonth()->utc();
        $live = Order::query()->goods()->where('status', '!=', 'cancelled'); // gift cards / Plus are not order revenue

        $todayN = (clone $live)->where('created_at', '>=', $today)->count();
        $monthN = (clone $live)->where('created_at', '>=', $month)->count();
        $revenue = (float) (clone $live)->where('created_at', '>=', $month)->sum('total_bdt');
        $aov = $monthN ? $revenue / $monthN : 0;
        $awaiting = Order::query()->goods()->whereIn('status', ['placed', 'verified'])->count();
        $transit = Order::query()->goods()->whereIn('status', ['india_hub', 'in_transit', 'dhaka_hub', 'out_for_delivery'])->count();

        $daily = collect(range(13, 0))->map(fn ($d) => (clone $live)->whereBetween('created_at', [now($tz)->subDays($d)->startOfDay()->utc(), now($tz)->subDays($d)->endOfDay()->utc()])->count())->all();

        return [
            Stat::make('Orders today', number_format($todayN))
                ->description(number_format($monthN).' this month')
                ->chart($daily)->color('primary')->icon(Heroicon::OutlinedShoppingBag),
            Stat::make('Revenue this month', Admin::bdt($revenue))
                ->description('Goods orders, excludes cancelled')->icon(Heroicon::OutlinedBanknotes)->color('success'),
            Stat::make('Average order value', Admin::bdt(round($aov)))
                ->description('This month')->icon(Heroicon::OutlinedCalculator),
            Stat::make('Awaiting purchase', number_format($awaiting))
                ->description('Placed or verified, not yet bought in India')
                ->color($awaiting > 0 ? 'warning' : 'success')->icon(Heroicon::OutlinedClock)
                ->url(OrderResource::getUrl('index', ['tab' => 'action'])),
            Stat::make('In transit', number_format($transit))
                ->description('Kolkata hub → delivery')->icon(Heroicon::OutlinedTruck)->color('info'),
            Stat::make('Delivered this month', number_format(Order::query()->goods()->where('status', 'delivered')->where('updated_at', '>=', $month)->count()))
                ->icon(Heroicon::OutlinedCheckCircle)->color('success'),
        ];
    }
}
