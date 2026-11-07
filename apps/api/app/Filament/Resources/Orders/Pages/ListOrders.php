<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Pages\PurchaseQueue;
use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** Board-style tabs: one per pipeline stage, with live counts. */
    public function getTabs(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $tabs = ['all' => Tab::make('All')->badge($counts->sum() ?: null)];
        $tabs['action'] = Tab::make('Needs action')->icon(Heroicon::OutlinedBolt)
            ->badge(($counts['placed'] ?? 0) + ($counts['verified'] ?? 0) + ($counts['purchased'] ?? 0) ?: null)->badgeColor('warning')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['placed', 'verified', 'purchased']));
        foreach (Admin::ORDER_STATUS_LABELS as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge($counts[$status] ?? null)
                ->badgeColor(Admin::ORDER_STATUS_COLORS[$status])
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'action';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('queue')->label('Purchase queue')->icon(Heroicon::OutlinedQueueList)->color('gray')
                ->url(PurchaseQueue::getUrl()),
            Action::make('exportAll')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => OrderActions::csv($this->getFilteredSortedTableQuery()->with(['user', 'items'])->limit(5000)->get())),
        ];
    }
}
