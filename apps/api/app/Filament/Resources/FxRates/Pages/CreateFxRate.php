<?php

namespace App\Filament\Resources\FxRates\Pages;

use App\Filament\Resources\FxRates\FxRateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;

class CreateFxRate extends CreateRecord
{
    protected static string $resource = FxRateResource::class;

    protected function afterCreate(): void
    {
        Cache::forget('fx:INRBDT');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
