<?php

namespace App\Filament\Resources\Coupons\Schemas;

use App\Filament\Support\Admin;
use App\Models\Coupon;
use App\Services\Commerce\Phone;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/** Coupons are fully controlled here; the storefront and apps only read them (POST /checkout/quote, GET /coupons/featured). */
class CouponForm
{
    public const TYPES = ['percent_items' => 'Percent off items', 'fixed' => 'Fixed amount off (BDT)', 'free_shipping' => 'Free shipping'];

    public static function stores(): array
    {
        return ['all' => 'All stores'] + Admin::MARKETS;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Discount')->columns(2)->schema([
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
                    ->helperText('Cap for a percent coupon; blank = no cap.')
                    ->visible(fn (Get $get) => $get('type') === 'percent_items'),
                TextInput::make('min_subtotal_bdt')->label('Minimum subtotal')->prefix('৳')->numeric()->minValue(0)->default(0)
                    ->helperText('Items subtotal (of the chosen store, if any) needed to apply it.'),
                Select::make('store')->options(self::stores())->default('all')->required()
                    ->helperText('The discount only counts that store\'s items / shipping.'),
                Toggle::make('active')->default(true),
            ]),
            Section::make('Schedule and limits')->columns(2)->schema([
                DateTimePicker::make('starts_at')->label('Starts')->seconds(false)->helperText('Bangladesh time; empty = now'),
                DateTimePicker::make('expires_at')->label('Ends')->seconds(false)->after('starts_at')->helperText('Bangladesh time; empty = no end'),
                TextInput::make('max_uses')->label('Total uses limit')->numeric()->integer()->minValue(1)->helperText('Blank = unlimited.'),
                TextInput::make('per_customer_limit')->label('Uses per customer')->numeric()->integer()->minValue(1)->default(1)
                    ->helperText('Blank = unlimited. Cancelled orders give the use back.'),
                TextInput::make('used')->label('Used so far')->numeric()->disabled()->default(0)->dehydrated(false),
            ]),
            Section::make('Who can use it')->columns(2)->schema([
                Toggle::make('first_order_only')->label('First order only')->helperText('Shopper has no earlier (non-cancelled) order.'),
                TagsInput::make('allowed_phones')->label('Only these customers (phone)')->placeholder('01XXXXXXXXX')
                    ->helperText('Leave empty for everyone. Restricted coupons never show as a promo.')
                    ->nestedRecursiveRules([fn () => function (string $attribute, mixed $value, \Closure $fail) {
                        if (! Phone::normalize((string) $value)) {
                            $fail("{$value} is not a valid Bangladeshi mobile number.");
                        }
                    }])
                    ->dehydrateStateUsing(fn ($state) => ($list = array_values(array_unique(array_filter(array_map(
                        fn ($p) => Phone::normalize((string) $p), (array) $state))))) ? $list : null),
            ]),
            Section::make('Storefront promo')->columns(2)->schema([
                Toggle::make('featured')->label('Show as promo')->live()->columnSpanFull()
                    ->helperText('Featured coupons appear on the web promo strip and in the app (when live and open to everyone).'),
                TextInput::make('promo_text_en')->label('Promo text (English)')->maxLength(120)->placeholder('10% off your first order')
                    ->visible(fn (Get $get) => (bool) $get('featured')),
                TextInput::make('promo_text_bn')->label('Promo text (Bangla)')->maxLength(120)->placeholder('প্রথম অর্ডারে ১০% ছাড়')
                    ->visible(fn (Get $get) => (bool) $get('featured')),
            ]),
        ]);
    }
}
