<?php

namespace App\Filament\Resources\TrackingEvents;

use App\Filament\Resources\TrackingEvents\Pages\ListTrackingEvents;
use App\Filament\Resources\TrackingEvents\Pages\ViewTrackingEvent;
use App\Filament\Resources\TrackingEvents\Schemas\TrackingEventInfolist;
use App\Filament\Resources\TrackingEvents\Tables\TrackingEventsTable;
use App\Models\TrackingEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only log of server-side tracking sends (Meta CAPI, GA4 MP, TikTok Events API, Delivro). */
class TrackingEventResource extends Resource
{
    protected static ?string $model = TrackingEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Tracking events';

    protected static ?string $modelLabel = 'tracking event';

    public static function infolist(Schema $schema): Schema
    {
        return TrackingEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrackingEventsTable::configure($table);
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
            'index' => ListTrackingEvents::route('/'),
            'view' => ViewTrackingEvent::route('/{record}'),
        ];
    }
}
