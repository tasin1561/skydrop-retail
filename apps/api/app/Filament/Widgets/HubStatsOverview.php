<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Hub\HubReceive;
use App\Filament\Resources\HubTasks\HubTaskResource;
use App\Filament\Support\FulfilmentAccess;
use App\Models\Fulfilment\HubReceipt;
use App\Models\Fulfilment\HubTask;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Kolkata hub at a glance: awaiting arrival, received today, discrepancies, average days purchase → hub. */
class HubStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = true;

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '120s';

    protected ?string $heading = 'Kolkata hub';

    public static function canView(): bool
    {
        return FulfilmentAccess::hub();
    }

    protected function getStats(): array
    {
        $today = now('Asia/Kolkata')->startOfDay()->utc();
        $awaiting = OrderItem::query()->whereNull('hub_received_at')->whereNull('cancelled_at')
            ->whereIn('order_id', Order::query()->goods()->select('id')->where('status', 'purchased'))->count();
        $receivedToday = HubReceipt::query()->where('received_at', '>=', $today)->count();
        $openTasks = HubTask::query()->where('status', 'open')->count();
        $discrepancies30 = HubReceipt::query()->where('condition', '!=', 'ok')->where('received_at', '>=', now()->subDays(30))->count();

        // Average days from "purchased" to "india_hub" over the last 30 days.
        $hub = OrderEvent::query()->where('status', 'india_hub')->where('at', '>=', now()->subDays(30))->pluck('at', 'order_id');
        $bought = OrderEvent::query()->where('status', 'purchased')->whereIn('order_id', $hub->keys())->pluck('at', 'order_id');
        $days = $hub->map(fn ($at, $id) => isset($bought[$id]) ? $bought[$id]->diffInHours($at) / 24 : null)->filter(fn ($d) => $d !== null);
        $avg = $days->isNotEmpty() ? round($days->avg(), 1) : null;

        return [
            Stat::make('Awaiting arrival', number_format($awaiting))->description('Items bought, not yet at the hub')
                ->icon(Heroicon::OutlinedTruck)->color('warning')->url(HubReceive::canAccess() ? HubReceive::getUrl() : null),
            Stat::make('Received today', number_format($receivedToday))->description('Kolkata time')->icon(Heroicon::OutlinedInboxArrowDown)->color('success'),
            Stat::make('Discrepancies', number_format($openTasks))->description($discrepancies30.' damaged / wrong in 30 days')
                ->icon(Heroicon::OutlinedExclamationTriangle)->color($openTasks ? 'danger' : 'gray')->url(HubTaskResource::canViewAny() ? HubTaskResource::getUrl() : null),
            Stat::make('Purchase → hub', $avg !== null ? $avg.' days' : '—')->description('Average, last 30 days')->icon(Heroicon::OutlinedClock),
        ];
    }
}
