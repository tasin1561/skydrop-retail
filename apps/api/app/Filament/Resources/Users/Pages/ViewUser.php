<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\CustomerActions;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CustomerActions::notify(),
            CustomerActions::adjustPoints(),
            CustomerActions::block(),
            EditAction::make(),
        ];
    }
}
