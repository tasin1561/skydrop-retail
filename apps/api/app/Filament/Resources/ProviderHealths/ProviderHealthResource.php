<?php

namespace App\Filament\Resources\ProviderHealths;

use App\Filament\Resources\ProviderHealths\Pages\CreateProviderHealth;
use App\Filament\Resources\ProviderHealths\Pages\EditProviderHealth;
use App\Filament\Resources\ProviderHealths\Pages\ListProviderHealths;
use App\Filament\Resources\ProviderHealths\Pages\ViewProviderHealth;
use App\Filament\Resources\ProviderHealths\Schemas\ProviderHealthForm;
use App\Filament\Resources\ProviderHealths\Schemas\ProviderHealthInfolist;
use App\Filament\Resources\ProviderHealths\Tables\ProviderHealthsTable;
use App\Models\ProviderHealth;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProviderHealthResource extends Resource
{
    protected static ?string $model = ProviderHealth::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ProviderHealthForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProviderHealthInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderHealthsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviderHealths::route('/'),
            'create' => CreateProviderHealth::route('/create'),
            'view' => ViewProviderHealth::route('/{record}'),
            'edit' => EditProviderHealth::route('/{record}/edit'),
        ];
    }
}
