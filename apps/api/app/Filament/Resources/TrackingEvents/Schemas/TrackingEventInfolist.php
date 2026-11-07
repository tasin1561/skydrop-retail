<?php

namespace App\Filament\Resources\TrackingEvents\Schemas;

use App\Filament\Resources\TrackingEvents\Tables\TrackingEventsTable;
use App\Filament\Support\Admin;
use App\Models\TrackingEvent;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TrackingEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Send')->columns(4)->schema([
                TextEntry::make('event')->badge(),
                TextEntry::make('destination')->badge(),
                TextEntry::make('status')->badge()->color(fn ($state) => TrackingEventsTable::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('response_code')->label('HTTP')->placeholder('—'),
                TextEntry::make('event_id')->copyable(),
                TextEntry::make('order_id')->placeholder('—'),
                TextEntry::make('attempts'),
                TextEntry::make('sent_at')->dateTime()->placeholder('not sent'),
                TextEntry::make('error')->columnSpanFull()->placeholder('—')->color('danger'),
            ]),
            Section::make('Payload (user data is SHA-256 hashed)')->collapsible()->schema([
                TextEntry::make('payload_json')->hiddenLabel()->state(fn (TrackingEvent $r) => Admin::jsonHtml($r->payload))->html(),
            ]),
        ]);
    }
}
