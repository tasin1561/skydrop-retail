<?php

namespace App\Filament\Resources\ProductWatches;

use App\Filament\Resources\ProductWatches\Pages\ListProductWatches;
use App\Filament\Resources\ProductWatches\Tables\ProductWatchesTable;
use App\Filament\Support\Admin;
use App\Models\ProductWatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ProductWatchResource extends Resource
{
    protected static ?string $model = ProductWatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Product watches';

    public static function table(Table $table): Table
    {
        return ProductWatchesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
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
            'index' => ListProductWatches::route('/'),
        ];
    }
}
