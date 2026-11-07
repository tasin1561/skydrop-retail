<?php

namespace App\Filament\Resources\PricingRules\Pages;

use App\Filament\Resources\PricingRules\PricingRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditPricingRule extends EditRecord
{
    protected function afterSave(): void
    {
        Cache::forget('pricing:rules');
    }

    protected function afterCreate(): void
    {
        Cache::forget('pricing:rules');
    }

    protected static string $resource = PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
