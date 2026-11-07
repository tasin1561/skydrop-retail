<?php

namespace App\Filament\Resources\Marketplaces\Pages;

use App\Filament\Resources\Marketplaces\MarketplaceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketplace extends ViewRecord
{
    protected static string $resource = MarketplaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
