<?php

namespace App\Filament\Resources\PricingRules\Pages;

use App\Filament\Resources\PricingRules\PricingRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingRule extends EditRecord
{
    protected function afterSave(): void
    {
        \Illuminate\Support\Facades\Cache::forget('pricing:rules');
    }

    protected function afterCreate(): void
    {
        \Illuminate\Support\Facades\Cache::forget('pricing:rules');
    }

    protected static string $resource = PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
