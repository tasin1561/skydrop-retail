<?php

namespace App\Filament\Resources\ProductWatches\Pages;

use App\Filament\Resources\ProductWatches\ProductWatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductWatches extends ListRecords
{
    protected static string $resource = ProductWatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
