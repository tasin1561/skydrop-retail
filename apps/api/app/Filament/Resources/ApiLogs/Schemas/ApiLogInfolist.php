<?php

namespace App\Filament\Resources\ApiLogs\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApiLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(4)->columnSpanFull()->schema([
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('type')->badge(),
                    TextEntry::make('market')->placeholder('—'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('ms')->suffix(' ms'),
                    IconEntry::make('rendered')->label('JS rendered')->boolean(),
                    TextEntry::make('cost_usd')->money('USD', 6),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('key')->columnSpanFull()->copyable(),
                    TextEntry::make('error')->columnSpanFull()->color('danger')->placeholder('—'),
                ]),
            ]);
    }
}
