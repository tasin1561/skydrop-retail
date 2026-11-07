<?php

namespace App\Filament\Resources\ProductRequests\Pages;

use App\Filament\Resources\ProductRequests\ProductRequestResource;
use App\Models\ProductRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProductRequests extends ListRecords
{
    protected static string $resource = ProductRequestResource::class;

    public function getTabs(): array
    {
        $counts = ProductRequest::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $tabs = ['all' => Tab::make('All')];
        foreach (ProductRequest::STATUSES as $k => $label) {
            $tabs[$k] = Tab::make($label)->badge($counts[$k] ?? null)->badgeColor(ProductRequestResource::COLORS[$k])
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $k));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'new';
    }
}
