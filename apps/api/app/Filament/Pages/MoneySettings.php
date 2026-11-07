<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Support\Admin;
use App\Filament\Support\MoneyAdmin;
use App\Services\Money\MoneySettings as Store;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
use UnitEnum;

/**
 * Wallet refunds, gift cards, Skydrop Plus reminders and card EMI (settings keys "money.*", App\Services\Money\MoneySettings).
 * The features themselves are switched on/off in Admin → System → Features (wallet, gift_cards, membership, pay.emi).
 */
class MoneySettings extends Page
{
    use HasPagePermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = MoneyAdmin::GROUP;

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Wallet, gift card & EMI settings';

    protected static ?string $navigationLabel = 'Settings';

    /** EMI tenures the admin can offer (SSLCommerz supports these). */
    public const TENURES = [3, 6, 9, 12, 18, 24];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $banks = array_map(function ($b) {
            $row = ['code' => $b['code'] ?? '', 'name' => $b['name'] ?? ''];
            foreach (self::TENURES as $t) {
                $row['rate_'.$t] = $b['rates'][(string) $t] ?? null;
            }

            return $row;
        }, (array) Store::get('emi.banks', []));

        $this->form->fill([
            'refund_default' => Store::get('wallet.refund_default'), 'refund_bonus_pct' => Store::get('wallet.refund_bonus_pct'),
            'gc_presets' => array_map('strval', (array) Store::get('gift_cards.presets')), 'gc_min' => Store::get('gift_cards.min_bdt'),
            'gc_max' => Store::get('gift_cards.max_bdt'), 'gc_expiry' => Store::get('gift_cards.expiry_months'),
            'reminder_days' => Store::get('membership.reminder_days'), 'credit_expiry_days' => Store::get('membership.credit_expiry_days'),
            'emi_enabled' => (bool) Store::get('emi.enabled'), 'emi_min' => Store::get('emi.min_order_bdt'),
            'emi_tenures' => array_map('intval', (array) Store::get('emi.tenures')), 'emi_banks' => $banks,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Skydrop Wallet refunds')->columns(2)
                ->description('Customers choose at cancel; staff choose in Refund / Cancel. This is the preselected option.')
                ->schema([
                    Select::make('refund_default')->label('Default refund destination')->required()
                        ->options(['wallet' => 'Skydrop Wallet (instant)', 'original' => 'Original payment method']),
                    TextInput::make('refund_bonus_pct')->label('Bonus for choosing the wallet')->suffix('%')->numeric()->minValue(0)->maxValue(50)->required(),
                ]),
            Section::make('Gift cards')->columns(4)->schema([
                TagsInput::make('gc_presets')->label('Preset amounts (৳)')->columnSpan(2)->placeholder('Add amount'),
                TextInput::make('gc_min')->label('Minimum')->prefix('৳')->numeric()->minValue(1)->required(),
                TextInput::make('gc_max')->label('Maximum')->prefix('৳')->numeric()->minValue(1)->required(),
                TextInput::make('gc_expiry')->label('Valid for')->suffix('months')->numeric()->minValue(0)->required()->helperText('0 = never expires'),
            ]),
            Section::make('Skydrop Plus')->columns(2)
                ->description('Renewal is manual (BD gateways have no reliable recurring billing): members get an SMS with a pay link before their plan ends.')
                ->schema([
                    TextInput::make('reminder_days')->label('Renewal reminder')->suffix('days before')->numeric()->minValue(1)->maxValue(30)->required(),
                    TextInput::make('credit_expiry_days')->label('Monthly wallet credit expires after')->suffix('days')->numeric()->minValue(0)->required()->helperText('0 = never'),
                ]),
            Section::make('Card EMI (SSLCommerz)')->columns(3)
                ->description('Charges are a % of the amount paid by card, paid by the customer and shown before they pay. Leave a rate empty if the bank does not offer that tenure.')
                ->schema([
                    Toggle::make('emi_enabled')->label('Offer card EMI')->helperText('Also needs the "Card EMI" feature switch.'),
                    TextInput::make('emi_min')->label('Minimum order')->prefix('৳')->numeric()->minValue(0)->required(),
                    CheckboxList::make('emi_tenures')->label('Tenures (months)')->columns(6)
                        ->options(array_combine(self::TENURES, array_map(fn ($t) => (string) $t, self::TENURES))),
                    Repeater::make('emi_banks')->label('Banks')->columnSpanFull()->columns(4)->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => $state['name'] ?? null)
                        ->schema([
                            TextInput::make('code')->required()->alphaDash()->maxLength(30),
                            TextInput::make('name')->required()->maxLength(80)->columnSpan(3),
                            ...array_map(fn ($t) => TextInput::make('rate_'.$t)->label($t.' months')->suffix('%')->numeric()->minValue(0)->maxValue(50), self::TENURES),
                        ]),
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
        $s = $this->form->getState();
        $banks = array_values(array_map(function ($b) {
            $rates = [];
            foreach (self::TENURES as $t) {
                if (($b['rate_'.$t] ?? null) !== null && $b['rate_'.$t] !== '') {
                    $rates[(string) $t] = (float) $b['rate_'.$t];
                }
            }

            return ['code' => (string) $b['code'], 'name' => (string) $b['name'], 'rates' => $rates];
        }, $s['emi_banks'] ?? []));

        Store::put('wallet.refund_default', $s['refund_default']);
        Store::put('wallet.refund_bonus_pct', (float) $s['refund_bonus_pct']);
        Store::put('gift_cards.presets', array_values(array_filter(array_map('intval', $s['gc_presets'] ?? []), fn ($n) => $n > 0)));
        Store::put('gift_cards.min_bdt', (int) $s['gc_min']);
        Store::put('gift_cards.max_bdt', (int) $s['gc_max']);
        Store::put('gift_cards.expiry_months', (int) $s['gc_expiry']);
        Store::put('membership.reminder_days', (int) $s['reminder_days']);
        Store::put('membership.credit_expiry_days', (int) $s['credit_expiry_days']);
        Store::put('emi.enabled', (bool) $s['emi_enabled']);
        Store::put('emi.min_order_bdt', (int) $s['emi_min']);
        Store::put('emi.tenures', array_values(array_map('intval', $s['emi_tenures'] ?? [])));
        Store::put('emi.banks', $banks);

        Notification::make()->title('Settings saved')->success()->send();
    }
}
