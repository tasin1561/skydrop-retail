<?php

namespace App\Filament\Resources\SupportRequests\Schemas;

use App\Filament\Resources\SupportRequests\Tables\SupportRequestsTable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columnSpanFull()->schema([
                    Select::make('status')->options(SupportRequestsTable::STATUSES)->required(),
                    Textarea::make('admin_note')->label('Note to customer')->rows(4),
                ]),
            ]);
    }
}
