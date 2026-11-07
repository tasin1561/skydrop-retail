<?php

namespace App\Filament\Resources\ProviderHealths;

use App\Filament\Resources\ProviderHealths\Pages\ListProviderHealths;
use App\Filament\Resources\ProviderHealths\Tables\ProviderHealthsTable;
use App\Filament\Support\Admin;
use App\Models\ProviderHealth;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ProviderHealthResource extends Resource
{
    protected static ?string $model = ProviderHealth::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Provider health';

    protected static ?string $modelLabel = 'provider';

    protected static ?string $pluralModelLabel = 'provider health';

    public static function table(Table $table): Table
    {
        return ProviderHealthsTable::configure($table);
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
            'index' => ListProviderHealths::route('/'),
        ];
    }
}
