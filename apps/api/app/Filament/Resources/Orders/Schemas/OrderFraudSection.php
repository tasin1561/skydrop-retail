<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Services\Fraud\FraudGate;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/** Order view: fraud score, level and reasons (fraud module). */
class OrderFraudSection
{
    public const COLORS = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'];

    public static function make(): Section
    {
        return Section::make('Fraud check')
            ->visible(fn (Order $r) => FraudGate::active() && $r->fraud_checked_at !== null)
            ->columns(2)
            ->schema([
                TextEntry::make('fraud_level')->label('Risk')->badge()
                    ->formatStateUsing(fn ($state, Order $r) => ucfirst((string) $state).' ('.$r->fraud_score.')')
                    ->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextEntry::make('fraud_review')->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Needs review' : 'Clear')
                    ->badge()->color(fn ($state) => $state ? 'danger' : 'gray'),
                TextEntry::make('fraud_reasons')->label('Reasons')->columnSpanFull()->listWithLineBreaks()->placeholder('No risk signals'),
                TextEntry::make('fraud_checked_at')->label('Checked')->since(),
            ]);
    }
}
