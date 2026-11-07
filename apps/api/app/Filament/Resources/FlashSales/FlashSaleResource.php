<?php

namespace App\Filament\Resources\FlashSales;

use App\Filament\Resources\FlashSales\Pages\CreateFlashSale;
use App\Filament\Resources\FlashSales\Pages\EditFlashSale;
use App\Filament\Resources\FlashSales\Pages\ListFlashSales;
use App\Filament\Resources\FlashSales\Schemas\FlashSaleForm;
use App\Filament\Resources\FlashSales\Tables\FlashSalesTable;
use App\Filament\Support\Admin;
use App\Models\FlashSale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FlashSaleResource extends Resource
{
    protected static ?string $model = FlashSale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return FlashSaleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlashSalesTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record); // RBAC: {slug}.ability via Gate
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlashSales::route('/'),
            'create' => CreateFlashSale::route('/create'),
            'edit' => EditFlashSale::route('/{record}/edit'),
        ];
    }
}
