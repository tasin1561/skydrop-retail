<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Editing is limited to staff notes, tracking and the delivery address; status and money change only through actions. */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Staff')->schema([
                    Textarea::make('staff_notes')->label('Internal notes (never shown to the customer)')->rows(5),
                ]),
                Section::make('Delivery')->columns(2)->schema([
                    TextInput::make('tracking_code')->maxLength(20)->unique(ignoreRecord: true),
                    TextInput::make('skydrop_consignment_id')->label('Skydrop consignment')->maxLength(255),
                    KeyValue::make('address')->columnSpanFull()->addable(false)->deletable(false)->editableKeys(false),
                ]),
            ]);
    }
}
