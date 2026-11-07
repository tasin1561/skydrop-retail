<?php

namespace App\Filament\Resources\MessageTemplates\Pages;

use App\Filament\Resources\MessageTemplates\MessageTemplateResource;
use App\Filament\Support\Admin;
use Database\Seeders\MessageTemplateSeeder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMessageTemplates extends ListRecords
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('seedDefaults')->label('Add missing defaults')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn () => Admin::canManage())
                ->requiresConfirmation()->modalDescription('Creates the built-in English and Bangla templates that are missing. Edited templates are not changed.')
                ->action(function () {
                    app(MessageTemplateSeeder::class)->run();
                    Notification::make()->title('Default templates added')->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
