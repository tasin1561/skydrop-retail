<?php

namespace App\Filament\Resources\PointsLedgers;

use App\Filament\Resources\PointsLedgers\Pages\ListPointsLedgers;
use App\Filament\Resources\PointsLedgers\Tables\PointsLedgersTable;
use App\Filament\Support\Admin;
use App\Models\PointsLedger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PointsLedgerResource extends Resource
{
    protected static ?string $slug = 'points-ledger';

    protected static ?string $model = PointsLedger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Points ledger';

    protected static ?string $modelLabel = 'points entry';

    protected static ?string $pluralModelLabel = 'points ledger';

    public static function table(Table $table): Table
    {
        return PointsLedgersTable::configure($table);
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
            'index' => ListPointsLedgers::route('/'),
        ];
    }
}
