<?php

namespace App\Filament\Resources\ReturnRequests\Pages;

use App\Filament\Resources\ReturnRequests\ReturnActions;
use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReturnRequest extends ViewRecord
{
    protected static string $resource = ReturnRequestResource::class;

    public function getTitle(): string
    {
        return 'Return #'.$this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return ReturnActions::all();
    }
}
