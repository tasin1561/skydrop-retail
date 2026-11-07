<?php

namespace App\Filament\Resources\ProviderHealths\Pages;

use App\Filament\Resources\ProviderHealths\ProviderHealthResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProviderHealth extends ViewRecord
{
    protected static string $resource = ProviderHealthResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
