<?php

namespace App\Filament\Resources\PointsLedgers\Pages;

use App\Filament\Resources\PointsLedgers\PointsLedgerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPointsLedgers extends ListRecords
{
    protected static string $resource = PointsLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
