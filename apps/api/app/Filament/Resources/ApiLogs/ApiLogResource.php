<?php

namespace App\Filament\Resources\ApiLogs;

use App\Filament\Resources\ApiLogs\Pages\ListApiLogs;
use App\Filament\Resources\ApiLogs\Pages\ViewApiLog;
use App\Filament\Resources\ApiLogs\Schemas\ApiLogInfolist;
use App\Filament\Resources\ApiLogs\Tables\ApiLogsTable;
use App\Filament\Support\Admin;
use App\Models\ApiLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ApiLogResource extends Resource
{
    protected static ?string $model = ApiLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'API logs';

    protected static ?string $modelLabel = 'API log';

    public static function infolist(Schema $schema): Schema
    {
        return ApiLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiLogsTable::configure($table);
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
            'index' => ListApiLogs::route('/'),
            'view' => ViewApiLog::route('/{record}'),
        ];
    }
}
