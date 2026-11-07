<?php

namespace App\Filament\Resources\Marketplaces\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MarketplaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Store')
                    ->columns(2)
                    ->schema([
                        TextInput::make('id')->label('Marketplace id')->disabled(),
                        TextInput::make('source')->label('Source domain')->disabled(),
                        TextInput::make('name')->label('Storefront name')->required()->maxLength(60),
                        TextInput::make('pincode')->label('Receiving pincode')->required()->length(6)->numeric()
                            ->helperText('Kolkata hub pincode used for price and delivery checks (700001).'),
                    ]),
                Section::make('Availability')
                    ->columns(2)
                    ->schema([
                        Callout::make('Turning a store OFF hides it for every shopper')
                            ->description('Search, product pages, cart and checkout stop showing this store immediately. Existing orders are not affected.')
                            ->warning()
                            ->columnSpanFull(),
                        Toggle::make('enabled')->label('Store ON for shoppers')->onColor('success')->offColor('danger')->inline(false),
                        Toggle::make('live_fetch')->label('Live fetch from providers')->inline(false)
                            ->helperText('OFF = catalog only (no paid Oxylabs calls). ON = live search, prices and verification.'),
                        TextInput::make('budget_share')->label('Share of monthly provider budget')->suffix('%')
                            ->numeric()->minValue(0)->maxValue(100)->required(),
                    ]),
                Section::make('Advanced settings')
                    ->collapsed()
                    ->schema([
                        KeyValue::make('settings')->label('Extra settings (JSON key/value)'),
                    ]),
            ]);
    }
}
