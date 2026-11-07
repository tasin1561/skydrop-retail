<?php

namespace App\Filament\Resources\PreorderRules\Pages;

use App\Filament\Resources\PreorderRules\PreorderRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPreorderRules extends ListRecords
{
    protected static string $resource = PreorderRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
