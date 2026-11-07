<?php

namespace App\Filament\Resources\Marketplaces;

use App\Filament\Resources\Marketplaces\Pages\EditMarketplace;
use App\Filament\Resources\Marketplaces\Pages\ListMarketplaces;
use App\Filament\Resources\Marketplaces\Schemas\MarketplaceForm;
use App\Filament\Resources\Marketplaces\Tables\MarketplacesTable;
use App\Filament\Support\Admin;
use App\Models\Marketplace;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MarketplaceResource extends Resource
{
    protected static ?string $model = Marketplace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MarketplaceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketplacesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketplaces::route('/'),
            'edit' => EditMarketplace::route('/{record}/edit'),
        ];
    }
}
