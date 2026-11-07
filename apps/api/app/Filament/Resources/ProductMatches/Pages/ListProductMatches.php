<?php

namespace App\Filament\Resources\ProductMatches\Pages;

use App\Filament\Resources\ProductMatches\ProductMatchResource;
use App\Jobs\Shopping\MatchProducts;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListProductMatches extends ListRecords
{
    protected static string $resource = ProductMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')->label('Run matcher now')->icon(Heroicon::OutlinedArrowPath)
                ->action(function () {
                    MatchProducts::dispatch();
                    Notification::make()->title('Matching queued')->body('New pairs appear here when the job finishes.')->success()->send();
                }),
        ];
    }
}
