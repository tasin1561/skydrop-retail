<?php

namespace App\Filament\Resources\ProviderHealths\Pages;

use App\Filament\Resources\ProviderHealths\ProviderHealthResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProviderHealths extends ListRecords
{
    protected static string $resource = ProviderHealthResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
