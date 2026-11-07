<?php

namespace App\Filament\Resources\FxRates\Schemas;

use App\Services\PricingService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FxRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Manual INR → BDT rate')
                    ->description(fn () => 'Current rate in use: '.number_format(app(PricingService::class)->fx(), 4).' BDT per ₹1. The newest rate wins.')
                    ->columns(2)
                    ->schema([
                        Hidden::make('pair')->default('INRBDT'),
                        TextInput::make('rate')->label('BDT per 1 INR')->numeric()->minValue(0.5)->maxValue(5)->step(0.00001)->required(),
                        TextInput::make('source')->default('manual')->maxLength(30)->required(),
                        DateTimePicker::make('fetched_at')->label('Effective from')->default(now())->required(),
                    ]),
            ]);
    }
}
