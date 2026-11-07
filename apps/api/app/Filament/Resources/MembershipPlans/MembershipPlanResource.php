<?php

namespace App\Filament\Resources\MembershipPlans;

use App\Filament\Resources\MembershipPlans\Pages\CreateMembershipPlan;
use App\Filament\Resources\MembershipPlans\Pages\EditMembershipPlan;
use App\Filament\Resources\MembershipPlans\Pages\ListMembershipPlans;
use App\Filament\Support\Admin;
use App\Filament\Support\MoneyAdmin;
use App\Models\MembershipPlan;
use App\Services\Money\MembershipService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/** Skydrop Plus plan builder: price, period and typed benefits (stored in membership_plans.benefits). */
class MembershipPlanResource extends Resource
{
    protected static ?string $model = MembershipPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = MoneyAdmin::GROUP;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Plus plans';

    protected static ?string $modelLabel = 'Plus plan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $b = fn (string $k) => 'benefits.'.$k;

        return $schema->columns(1)->components([
            Section::make('Plan')->columns(3)->schema([
                TextInput::make('name')->required()->maxLength(80)->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => $get('slug') ? null : $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->required()->maxLength(80)->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('tagline')->maxLength(160),
                TextInput::make('price_bdt')->label('Price')->prefix('৳')->numeric()->minValue(0)->required(),
                Select::make('period')->options(MembershipPlan::PERIODS)->required()->default('monthly'),
                TextInput::make('sort')->numeric()->default(0),
                Toggle::make('active')->default(true),
                Toggle::make('highlight')->label('Highlight ("most popular", used for the cart nudge)'),
            ]),
            Section::make('Shipping')->columns(3)->schema([
                Toggle::make($b('free_shipping'))->label('Free shipping on every order')->live(),
                TextInput::make($b('free_shipping_threshold_bdt'))->label('…or free per store parcel from')->prefix('৳')->numeric()->minValue(0)->default(0)
                    ->hidden(fn (Get $get) => (bool) $get($b('free_shipping')))->helperText('0 = off'),
                TextInput::make($b('shipping_discount_pct'))->label('Shipping discount')->suffix('%')->numeric()->minValue(0)->maxValue(100)->default(0)
                    ->hidden(fn (Get $get) => (bool) $get($b('free_shipping'))),
            ]),
            Section::make('Savings & rewards')->columns(3)->schema([
                TextInput::make($b('extra_discount_pct'))->label('Extra discount on items')->suffix('%')->numeric()->minValue(0)->maxValue(100)->default(0),
                TextInput::make($b('extra_discount_cap_bdt'))->label('Extra discount cap per order')->prefix('৳')->numeric()->minValue(0)->default(0)->helperText('0 = no cap'),
                TextInput::make($b('points_multiplier'))->label('Points multiplier')->suffix('×')->numeric()->minValue(1)->maxValue(10)->step(0.1)->default(1),
                TextInput::make($b('monthly_wallet_credit_bdt'))->label('Monthly wallet credit')->prefix('৳')->numeric()->minValue(0)->default(0),
                TextInput::make($b('early_flash_sale_access_minutes'))->label('Early flash-sale access')->suffix('min')->numeric()->minValue(0)->default(0),
            ]),
            Section::make('Service')->columns(3)->schema([
                Toggle::make($b('free_unboxing'))->label('Free unboxing video'),
                Toggle::make($b('free_returns'))->label('Free returns'),
                Toggle::make($b('priority_support'))->label('Priority support'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')
            ->columns([
                TextColumn::make('name')->weight('bold')->description(fn (MembershipPlan $r) => $r->slug),
                TextColumn::make('price_bdt')->label('Price')->formatStateUsing(fn ($state, MembershipPlan $r) => Admin::bdt($state).' / '.strtolower(MembershipPlan::PERIODS[$r->period] ?? $r->period)),
                TextColumn::make('benefits')->label('Benefits')->wrap()
                    ->state(fn (MembershipPlan $r) => implode(' · ', MembershipService::describe(MembershipService::normalize($r->benefits ?? []))) ?: '—'),
                TextColumn::make('memberships_count')->counts(['memberships' => fn ($q) => $q->whereIn('status', ['active', 'cancelled'])->where('renews_at', '>', now())])->label('Members'),
                ToggleColumn::make('highlight'),
                ToggleColumn::make('active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function canDelete(Model $record): bool
    {
        return false; // deactivate instead (memberships reference plans)
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembershipPlans::route('/'),
            'create' => CreateMembershipPlan::route('/create'),
            'edit' => EditMembershipPlan::route('/{record}/edit'),
        ];
    }
}
