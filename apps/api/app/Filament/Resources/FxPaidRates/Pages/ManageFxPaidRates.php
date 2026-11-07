<?php

namespace App\Filament\Resources\FxPaidRates\Pages;

use App\Filament\Resources\FxPaidRates\FxPaidRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFxPaidRates extends ManageRecords
{
    protected static string $resource = FxPaidRateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
