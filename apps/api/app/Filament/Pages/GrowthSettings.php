<?php

namespace App\Filament\Pages;

use App\Filament\Support\Admin;
use App\Models\Category;
use App\Models\Setting;
use App\Services\Growth\GrowthSettings as Growth;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * Affiliate and referral settings (settings table keys, see App\Services\Growth\GrowthSettings).
 * Form state uses "__" instead of "." so dotted keys stay flat.
 */
class GrowthSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Growth';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Growth settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Admin::can('page.growth-settings'); // RBAC
    }

    /** @return array<string, string> category slug => label */
    public static function categories(): array
    {
        try {
            $cats = Category::query()->orderBy('name')->get();
        } catch (Throwable) {
            return [];
        }

        // Categories are per marketplace; products carry the slug, so one rate per slug.
        return $cats->filter(fn ($c) => preg_match('/^[a-z0-9_-]+$/i', (string) $c->slug))
            ->mapWithKeys(fn ($c) => [(string) $c->slug => (string) $c->name])->all();
    }

    /** @return array<int, string> every settings key this page edits */
    public static function keys(): array
    {
        return array_merge(Growth::KEYS, array_map(fn ($c) => 'affiliate.rates.'.$c, array_keys(self::categories())));
    }

    private static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function mount(): void
    {
        $state = [];
        foreach (self::keys() as $key) {
            $state[self::field($key)] = str_starts_with($key, 'affiliate.rates.') && $key !== 'affiliate.rates.default'
                ? Setting::get($key) : Growth::get($key);
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $num = fn (string $key, string $label) => TextInput::make(self::field($key))->label($label)->numeric()->minValue(0)->required();
        $rates = collect(self::categories())->map(fn ($label, $slug) => TextInput::make(self::field('affiliate.rates.'.$slug))->label($label)
            ->numeric()->minValue(0)->maxValue(50)->suffix('%')->placeholder('Default'))->values()->all();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Affiliate program')
                    ->description('Commission = items after discounts (no shipping) × rate. Attribution: last click within the cookie window.')
                    ->columns(3)
                    ->schema([
                        $num('affiliate.rates.default', 'Default rate')->suffix('%')->maxValue(50),
                        $num('affiliate.cookie_days', 'Cookie window')->suffix('days')->minValue(1)->maxValue(365),
                        $num('affiliate.hold_days', 'Hold after delivery')->suffix('days')->maxValue(90)
                            ->helperText('Approved after this many days, so returns can be reversed.'),
                        $num('affiliate.min_payout_bdt', 'Minimum payout')->prefix('৳'),
                        $num('affiliate.max_per_order_bdt', 'Cap per order')->prefix('৳')->helperText('0 = no cap'),
                    ]),
                Section::make('Rates by category')
                    ->description('Empty uses the default rate. An affiliate\'s own rate (Affiliates) overrides these.')
                    ->columns(4)->collapsible()
                    ->schema($rates ?: [TextInput::make('no_categories')->label('No categories yet')->disabled()->dehydrated(false)]),
                Section::make('Refer a friend')
                    ->description('Points are given once, when the invited friend\'s first order is delivered.')
                    ->columns(2)
                    ->schema([
                        $num('referral.inviter_points', 'Inviter gets')->suffix('points'),
                        $num('referral.friend_points', 'Friend gets')->suffix('points'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save settings')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        foreach (self::keys() as $key) {
            $value = $state[self::field($key)] ?? null;
            if ($value === null || $value === '') {
                Setting::query()->whereKey($key)->delete(); // fall back to config/growth.php
            } else {
                Setting::put($key, $value + 0);
            }
        }
        Notification::make()->title('Growth settings saved')->success()->send();
    }
}
