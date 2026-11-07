<?php

namespace App\Filament\Pages\Hub;

use App\Filament\Concerns\HasPagePermission;
use App\Services\Fulfilment\FulfilmentSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use UnitEnum;

/** Fulfilment settings (settings table "fulfilment.*"). Features themselves are switched in System → Features. */
class FulfilmentSettingsPage extends Page
{
    use HasPagePermission;

    /** @var list<string> */
    protected static array $requiredPermissions = ['settings.manage'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Hub';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Fulfilment settings';

    protected static ?string $title = 'Fulfilment settings';

    protected static ?string $slug = 'fulfilment-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $state = [];
        foreach (FulfilmentSettings::KEYS as $k) {
            Arr::set($state, $k, FulfilmentSettings::get($k));
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(2)->components([
            Section::make('Kolkata hub')->schema([
                TextInput::make('hub.weight_variance_pct')->label('Flag weight variance above')->suffix('%')->numeric()->minValue(0)->maxValue(500)->required()
                    ->helperText('Measured weight vs the estimate used at checkout. Flagged orders can be charged or absorbed.'),
            ]),
            Section::make('Paid unboxing')->schema([
                TextInput::make('unboxing.fee_bdt')->label('Fee')->prefix('৳')->numeric()->minValue(0)->required(),
                Radio::make('unboxing.per')->label('Charged per')->options(['shipment' => 'Store shipment', 'item' => 'Item (unit)'])->required(),
                TextInput::make('unboxing.auto_approve_hours')->label('Auto-approve after')->suffix('hours')->numeric()->minValue(1)->maxValue(240)->required(),
            ]),
            Section::make('Returns')->schema([
                TextInput::make('returns.window_days')->label('Return window after delivery')->suffix('days')->numeric()->minValue(0)->maxValue(60)->required(),
                Toggle::make('returns.changed_mind')->label('Accept "changed my mind" returns'),
                TextInput::make('returns.shipping_fee_bdt')->label('Return shipping fee')->prefix('৳')->numeric()->minValue(0)->required()
                    ->helperText('Deducted from the refund for "size issue" / "changed my mind" returns. Damaged or wrong items are free; Skydrop Plus members with free returns never pay it.'),
            ]),
            Section::make('Supplier price alerts')->schema([
                TextInput::make('price_alerts.rise_pct')->label('Alert when the source price rises by more than')->suffix('%')->numeric()->minValue(0)->maxValue(100)->required(),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save settings')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        foreach (FulfilmentSettings::KEYS as $k) {
            $v = Arr::get($state, $k);
            FulfilmentSettings::put($k, is_numeric($v) ? $v + 0 : $v);
        }
        Notification::make()->title('Settings saved')->success()->send();
    }
}
