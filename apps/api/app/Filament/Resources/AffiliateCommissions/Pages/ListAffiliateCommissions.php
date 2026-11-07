<?php

namespace App\Filament\Resources\AffiliateCommissions\Pages;

use App\Filament\Resources\AffiliateCommissions\AffiliateCommissionResource;
use App\Models\AffiliateCommission;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAffiliateCommissions extends ListRecords
{
    protected static string $resource = AffiliateCommissionResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];
        foreach (AffiliateCommission::STATUSES as $k => $label) {
            $tabs[$k] = Tab::make($label)->modifyQueryUsing(fn (Builder $q) => $q->where('status', $k));
        }

        return $tabs;
    }
}
