<?php

namespace App\Filament\Resources\BlockedEntries;

use App\Filament\Resources\BlockedEntries\Pages\ManageBlockedEntries;
use App\Models\BlockedEntry;
use App\Support\AdminSecurity;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Blocked phones, IPs and devices (fraud check, honeypot, staff). Values are encrypted at rest. */
class BlockedEntryResource extends Resource
{
    protected static ?string $model = BlockedEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Blocklist';

    protected static ?string $modelLabel = 'blocked entry';

    protected static ?string $slug = 'blocklist';

    public static function canViewAny(): bool
    {
        return AdminSecurity::can('fraud.override');
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
            Select::make('type')->options(BlockedEntry::TYPES)->required(),
            TextInput::make('value')->required()->maxLength(120)->helperText('Phone (01XXXXXXXXX), IP address or device id.'),
            TextInput::make('reason')->maxLength(250),
            DateTimePicker::make('expires_at')->label('Expires')->helperText('Empty = permanent.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('value')->state(fn (BlockedEntry $r) => $r->value)->copyable(),
                TextColumn::make('reason')->limit(50)->placeholder('—'),
                TextColumn::make('source')->badge()->color(fn ($state) => $state === 'honeypot' ? 'danger' : 'gray'),
                TextColumn::make('expires_at')->label('Expires')->since()->placeholder('never'),
                TextColumn::make('created_at')->label('Added')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(BlockedEntry::TYPES),
                SelectFilter::make('source')->options(['staff' => 'Staff', 'honeypot' => 'Honeypot', 'bot' => 'Bot', 'fraud' => 'Fraud']),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBlockedEntries::route('/')];
    }
}
