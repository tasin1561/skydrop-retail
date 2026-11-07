<?php

namespace App\Filament\Resources\SupportRequests\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SupportRequests\Tables\SupportRequestsTable;
use App\Models\SupportRequest;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(4)->columnSpanFull()->schema([
                    TextEntry::make('type')->badge()->formatStateUsing(fn ($state) => SupportRequestsTable::TYPES[$state] ?? $state),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => SupportRequestsTable::STATUSES[$state] ?? $state)
                        ->color(fn ($state) => SupportRequestsTable::COLORS[$state] ?? 'gray'),
                    TextEntry::make('order_id')->label('Order')->url(fn (SupportRequest $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
                    TextEntry::make('user.name')->label('Customer')->helperText(fn (SupportRequest $r) => $r->user?->phone),
                    TextEntry::make('message')->columnSpanFull()->prose(),
                    ImageEntry::make('photos')->columnSpanFull()->imageHeight(140)->placeholder('No photos'),
                    TextEntry::make('admin_note')->label('Note to customer')->columnSpanFull()->placeholder('—'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->since(),
                ]),
            ]);
    }
}
