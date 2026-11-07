<?php

namespace App\Filament\Resources\Affiliates\Pages;

use App\Filament\Resources\Affiliates\AffiliateResource;
use App\Models\Affiliate;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAffiliates extends ListRecords
{
    protected static string $resource = AffiliateResource::class;

    public function getTabs(): array
    {
        $counts = Affiliate::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $tabs = ['all' => Tab::make('All')];
        foreach (Affiliate::STATUSES as $k => $label) {
            $tabs[$k] = Tab::make($label)->badge($counts[$k] ?? null)->modifyQueryUsing(fn (Builder $q) => $q->where('status', $k));
        }

        return $tabs;
    }
}
