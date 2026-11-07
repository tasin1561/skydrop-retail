<?php

namespace App\Filament\Resources\FxPaidRates;

use App\Filament\Resources\FxPaidRates\Pages\ManageFxPaidRates;
use App\Models\FxPaidRate;
use App\Support\AdminSecurity;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** BDT per INR actually paid per day; the profit report uses the latest rate on or before each order's date. */
class FxPaidRateResource extends Resource
{
    protected static ?string $model = FxPaidRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyRupee;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'FX paid';

    protected static ?string $modelLabel = 'FX paid rate';

    public static function canViewAny(): bool
    {
        return AdminSecurity::can('reports.profit');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->required()->unique(ignoreRecord: true)->default(now()),
            TextInput::make('rate')->label('BDT per INR paid')->numeric()->minValue(0.5)->maxValue(5)->step(0.0001)->required(),
            TextInput::make('note')->maxLength(250)->placeholder('e.g. bank TT, hundi, card'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('date', 'desc')->columns([
            TextColumn::make('date')->date()->sortable(),
            TextColumn::make('rate')->numeric(4),
            TextColumn::make('note')->placeholder('—'),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFxPaidRates::route('/')];
    }
}
