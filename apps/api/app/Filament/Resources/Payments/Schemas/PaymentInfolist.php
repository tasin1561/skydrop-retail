<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Models\Payment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(4)->columnSpanFull()->schema([
                    TextEntry::make('order_id')->label('Order')->url(fn (Payment $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
                    TextEntry::make('gateway')->badge(),
                    TextEntry::make('kind')->placeholder('full'),
                    TextEntry::make('status')->badge()->color(fn ($state) => Admin::PAYMENT_STATUS_COLORS[$state] ?? 'gray'),
                    TextEntry::make('amount_bdt')->label('Amount')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                    TextEntry::make('refunded_bdt')->label('Refunded')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                    TextEntry::make('gateway_ref')->copyable()->placeholder('—'),
                    TextEntry::make('refund_ref')->placeholder('—'),
                    TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                    TextEntry::make('created_at')->dateTime(),
                ]),
                Section::make('Gateway payload')->columnSpanFull()->collapsible()->schema([
                    TextEntry::make('payload_json')->hiddenLabel()->state(fn (Payment $r) => Admin::jsonHtml($r->payload ?? [])),
                ]),
            ]);
    }
}
