<?php

namespace App\Filament\Resources\PricingRules\Pages;

use App\Filament\Resources\PricingRules\PricingRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePricingRule extends CreateRecord
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
}
