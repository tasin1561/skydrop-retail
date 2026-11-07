<?php

namespace App\Filament\Resources\FxRates;

use App\Filament\Resources\FxRates\Pages\CreateFxRate;
use App\Filament\Resources\FxRates\Pages\ListFxRates;
use App\Filament\Resources\FxRates\Schemas\FxRateForm;
use App\Filament\Resources\FxRates\Tables\FxRatesTable;
use App\Filament\Support\Admin;
use App\Models\FxRate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FxRateResource extends Resource
{
    protected static ?string $model = FxRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'FX rates';

    protected static ?string $modelLabel = 'FX rate';

    public static function form(Schema $schema): Schema
    {
        return FxRateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FxRatesTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return Admin::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFxRates::route('/'),
            'create' => CreateFxRate::route('/create'),
        ];
    }
}
