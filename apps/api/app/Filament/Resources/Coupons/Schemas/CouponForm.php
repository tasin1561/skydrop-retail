<?php

namespace App\Filament\Resources\Coupons\Schemas;

use App\Models\Coupon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CouponForm
{
    public const TYPES = ['percent_items' => 'Percent off items', 'fixed' => 'Fixed amount off (BDT)', 'free_shipping' => 'Free shipping'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(2)->schema([
                    TextInput::make('code')->required()->maxLength(30)
                        ->regex('/^\s*[A-Za-z0-9_-]+\s*$/')->validationMessages(['regex' => 'Use letters, numbers, - and _ only (no spaces).'])
                        // Codes are stored upper-case, so "skydrop10" must clash with "SKYDROP10".
                        ->rule(fn (?Coupon $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                            $taken = Coupon::query()->whereRaw('upper(code) = ?', [strtoupper(trim((string) $value))])
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists();
                            if ($taken) {
                                $fail('This code already exists.');
                            }
                        })
                        ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state))),
                    Select::make('type')->options(self::TYPES)->required()->live(),
                    TextInput::make('value')->numeric()->minValue(1)->default(0)
                        ->required(fn (Get $get) => $get('type') !== 'free_shipping')
                        ->maxValue(fn (Get $get) => $get('type') === 'percent_items' ? 100 : null)
                        ->suffix(fn (Get $get) => $get('type') === 'percent_items' ? '%' : '৳')
                        ->hidden(fn (Get $get) => $get('type') === 'free_shipping'),
                    TextInput::make('max_discount_bdt')->label('Max discount')->prefix('৳')->numeric()->minValue(0)
                        ->visible(fn (Get $get) => $get('type') === 'percent_items'),
                    TextInput::make('min_subtotal_bdt')->label('Minimum subtotal')->prefix('৳')->numeric()->minValue(0)->default(0),
                    TextInput::make('max_uses')->label('Max uses (blank = unlimited)')->numeric()->minValue(1),
                    DateTimePicker::make('expires_at'),
                    TextInput::make('used')->numeric()->disabled()->default(0)->dehydrated(false),
                    Toggle::make('active')->default(true),
                ]),
            ]);
    }
}
