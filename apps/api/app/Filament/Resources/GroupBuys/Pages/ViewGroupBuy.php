<?php

namespace App\Filament\Resources\GroupBuys\Pages;

use App\Filament\Resources\GroupBuys\GroupBuyResource;
use Filament\Resources\Pages\ViewRecord;

class ViewGroupBuy extends ViewRecord
{
    protected static string $resource = GroupBuyResource::class;

    protected function getHeaderActions(): array
    {
        return [GroupBuyResource::forceClose(), GroupBuyResource::cancel()];
    }
}
