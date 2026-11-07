<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Support\Admin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Listing')
                    ->description('Prices and details come from the source store; edit them only to fix bad data. The next refresh overwrites them.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('id')->disabled(),
                        TextInput::make('market')->formatStateUsing(fn ($state) => Admin::marketLabel($state))->disabled(),
                        TextInput::make('title')->required()->maxLength(500)->columnSpanFull(),
                        TextInput::make('brand')->maxLength(255),
                        TextInput::make('category')->required()->maxLength(40),
                        TextInput::make('price_inr')->label('Price')->prefix('₹')->numeric()->required(),
                        TextInput::make('mrp_inr')->label('MRP')->prefix('₹')->numeric(),
                    ]),
                Section::make('Visibility')
                    ->columns(3)
                    ->schema([
                        Toggle::make('hidden')->label('Hidden from listings')
                            ->helperText('Product page stays reachable (SEO) but it is not listed or sold.'),
                        Toggle::make('in_stock'),
                        Toggle::make('pincode_verified')->label('Deliverable to 700001'),
                    ]),
            ]);
    }
}
