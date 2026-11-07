<?php

namespace App\Filament\Resources\RestrictedRules\Pages;

use App\Filament\Resources\RestrictedRules\RestrictedRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRestrictedRule extends EditRecord
{
    protected static string $resource = RestrictedRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
