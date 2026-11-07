<?php

namespace App\Filament\Pages;

use App\Filament\Support\Admin;
use App\Filament\Support\TrackingSettingsSection;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Runtime settings stored in the `settings` table (App\Models\Setting). Keys mirror config/retail.php paths,
 * e.g. "oxylabs.monthly_budget_usd"; modules read Setting::get($key, config("retail.$key")).
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 9;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Admin::canManage();
    }

    public function mount(): void
    {
        $state = [];
        foreach (array_keys(Admin::SETTINGS) as $key) {
            Arr::set($state, $key, Admin::setting($key));
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $minutes = fn (string $key, string $label) => TextInput::make($key)->label($label)->numeric()->minValue(1)->suffix('min')->required();

        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Section::make('Oxylabs budget and speed')
                    ->description('Oxylabs is paid per call. The dashboard warns at 70%, 90% and 100% of the monthly budget.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('oxylabs.monthly_budget_usd')->label('Monthly budget')->prefix('$')->numeric()->minValue(0)->required(),
                        TextInput::make('oxylabs.hedge_after')->label('Hedge after')->suffix('sec')->numeric()->minValue(0)->maxValue(60)->required()
                            ->helperText('Start a backup provider call if the first is slower than this. 0 = off (cheaper).'),
                        TextInput::make('prefetch_top')->label('Prefetch top N results')->numeric()->minValue(0)->maxValue(20)->required()
                            ->helperText('Product pages fetched in advance for each new search (each is a paid call).'),
                    ]),
                Section::make('Variants')
                    ->columns(2)
                    ->schema([
                        Select::make('variants.mode')->label('Variant fetching')->required()->live()
                            ->options(['eager' => 'Eager: fetch all variants when a product is opened', 'intent' => 'Intent: fetch a variant only when the shopper picks it']),
                        TextInput::make('variants.max')->label('Max variants per product')->numeric()->minValue(1)->maxValue(200)->required()
                            ->helperText(fn (Get $get) => $get('variants.mode') === 'eager' ? 'Upper bound of paid calls per product page.' : null),
                    ]),
                Section::make('Cache freshness (minutes)')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        $minutes('ttl.search_fresh', 'Search fresh'),
                        $minutes('ttl.search_stale', 'Search stale (served while refreshing)'),
                        $minutes('ttl.product_fresh', 'Product fresh'),
                        $minutes('ttl.product_stale', 'Product stale'),
                        $minutes('ttl.sibling_fresh', 'Variant siblings fresh'),
                        $minutes('ttl.details_fresh', 'Details fresh'),
                    ]),
                Section::make('Shipping (India → Bangladesh)')
                    ->columns(3)
                    ->schema([
                        TextInput::make('pricing.shipping.base_bdt')->label('Base')->prefix('৳')->numeric()->minValue(0)->required(),
                        TextInput::make('pricing.shipping.per_kg_bdt')->label('Per kg')->prefix('৳')->numeric()->minValue(0)->required(),
                        TextInput::make('pricing.shipping.free_above_bdt')->label('Free above')->prefix('৳')->numeric()->minValue(0)->required(),
                    ]),
                Section::make('Pricing and alerts')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pricing.fx_buffer')->label('FX buffer')->numeric()->minValue(0)->maxValue(0.2)->step(0.001)->required()
                            ->helperText('Fraction added on top of the INR→BDT rate, e.g. 0.015 = 1.5%.'),
                        TextInput::make('alerts.telegram_chat')->label('Alert Telegram chat id')->maxLength(64)
                            ->helperText('Budget, provider-down and low-margin alerts are posted here.'),
                    ]),
                TrackingSettingsSection::make(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(Admin::canManage(), 403);
        $state = $this->form->getState();
        $numeric = ['oxylabs.monthly_budget_usd', 'oxylabs.hedge_after', 'variants.max', 'prefetch_top', 'pricing.fx_buffer'];
        foreach (array_keys(Admin::SETTINGS) as $key) {
            $value = Arr::get($state, $key);
            if (str_starts_with($key, 'ttl.') || str_starts_with($key, 'pricing.shipping.') || in_array($key, $numeric, true)) {
                $value = $value === null || $value === '' ? null : $value + 0;
            }
            if ($value === null || $value === '') {
                Setting::query()->whereKey($key)->delete(); // fall back to config/retail.php
            } else {
                Setting::put($key, $value);
            }
            Cache::forget("setting:{$key}");
        }
        Cache::forget('settings:all');
        Cache::forget('fx:INRBDT');

        Notification::make()->title('Settings saved')->success()->send();
    }
}
