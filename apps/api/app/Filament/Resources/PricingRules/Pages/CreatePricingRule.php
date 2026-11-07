<?php

namespace App\Filament\Resources\PricingRules\Pages;

use App\Filament\Resources\PricingRules\PricingRuleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;

class CreatePricingRule extends CreateRecord
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
}
