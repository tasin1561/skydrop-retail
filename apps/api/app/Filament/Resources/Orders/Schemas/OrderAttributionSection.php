<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Services\Tracking\Attribution;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/** Order view: where the shopper came from (orders.attribution, captured by the storefront). */
class OrderAttributionSection
{
    public static function make(): Section
    {
        $touch = fn (string $key) => fn (Order $r) => $r->attribution['last'][$key] ?? $r->attribution['first'][$key] ?? null;

        return Section::make('Attribution')
            ->collapsible()
            ->columns(2)
            ->schema([
                TextEntry::make('attr_source')->label('Source')->badge()->state(fn (Order $r) => Attribution::source($r->attribution)['source']),
                TextEntry::make('attr_medium')->label('Medium')->placeholder('—')->state(fn (Order $r) => Attribution::source($r->attribution)['medium']),
                TextEntry::make('attr_campaign')->label('Campaign')->placeholder('—')->columnSpanFull()
                    ->state(fn (Order $r) => Attribution::source($r->attribution)['campaign']),
                TextEntry::make('attr_first')->label('First touch')->placeholder('—')->columnSpanFull()
                    ->state(fn (Order $r) => self::touchText($r->attribution['first'] ?? null)),
                TextEntry::make('attr_fbclid')->label('fbclid')->placeholder('—')->limit(20)->copyable()->state($touch('fbclid')),
                TextEntry::make('attr_gclid')->label('gclid')->placeholder('—')->limit(20)->copyable()->state($touch('gclid')),
                TextEntry::make('attr_ttclid')->label('ttclid')->placeholder('—')->limit(20)->copyable()->state($touch('ttclid')),
                TextEntry::make('attr_landing')->label('Landing page')->placeholder('—')->limit(40)
                    ->state(fn (Order $r) => $r->attribution['last']['landing'] ?? $r->attribution['first']['landing'] ?? null),
                TextEntry::make('attr_consent')->label('Cookie consent')->placeholder('unknown')->state(fn (Order $r) => $r->attribution['consent'] ?? null),
            ]);
    }

    private static function touchText(?array $t): ?string
    {
        if (! $t) {
            return null;
        }
        $s = Attribution::source(['first' => $t]);

        return collect([$s['source'], $s['medium'], $s['campaign'], isset($t['at']) ? 'on '.substr((string) $t['at'], 0, 10) : null])->filter()->implode(' · ');
    }
}
