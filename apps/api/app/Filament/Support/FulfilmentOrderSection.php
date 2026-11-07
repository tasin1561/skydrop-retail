<?php

namespace App\Filament\Support;

use App\Models\Fulfilment\HubReceipt;
use App\Models\Fulfilment\ReturnRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;

/** Order view: supplier price alerts (red banner), weight flag, unboxing state, hub receipts and return cases. */
class FulfilmentOrderSection
{
    public static function make(): Section
    {
        return Section::make('Fulfilment')
            ->description('Kolkata hub, unboxing, price alerts and returns')
            ->collapsible()
            ->visible(fn (Order $record) => self::hasAnything($record))
            ->schema([View::make('filament.hub.order-fulfilment')]);
    }

    public static function hasAnything(Order $o): bool
    {
        return $o->price_alert || $o->weight_flag || $o->unboxing
            || HubReceipt::query()->where('order_id', $o->id)->exists()
            || ReturnRequest::query()->where('order_id', $o->id)->exists()
            || OrderItem::query()->where('order_id', $o->id)->whereNotNull('cancelled_at')->exists();
    }
}
