<?php

namespace App\Filament\Resources\ProviderHealths\Pages;

use App\Filament\Resources\ProviderHealths\ProviderHealthResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProviderHealth extends EditRecord
{
    protected static string $resource = ProviderHealthResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
