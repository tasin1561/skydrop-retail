<?php

namespace App\Filament\Resources\SupportRequests\Pages;

use App\Filament\Resources\SupportRequests\SupportRequestResource;
use App\Filament\Resources\SupportRequests\Tables\SupportRequestsTable;
use App\Models\SupportRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSupportRequests extends ListRecords
{
    protected static string $resource = SupportRequestResource::class;

    public function getTabs(): array
    {
        $counts = SupportRequest::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $tabs = ['all' => Tab::make('All')];
        foreach (SupportRequestsTable::STATUSES as $k => $label) {
            $tabs[$k] = Tab::make($label)->badge($counts[$k] ?? null)->badgeColor(SupportRequestsTable::COLORS[$k])
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', $k));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }
}
