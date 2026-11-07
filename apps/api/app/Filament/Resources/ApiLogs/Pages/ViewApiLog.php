<?php

namespace App\Filament\Resources\ApiLogs\Pages;

use App\Filament\Resources\ApiLogs\ApiLogResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewApiLog extends ViewRecord
{
    protected static string $resource = ApiLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
