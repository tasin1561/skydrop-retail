<?php

namespace App\Filament\Pages;

use App\Support\AdminSecurity;
use App\Filament\Concerns\HasPagePermission;
use App\Models\Setting;
use App\Services\Fraud\FraudService;
use App\Support\Knobs;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;
use UnitEnum;

/**
 * Runtime knobs for profit, fraud and admin security (settings table keys "finance.*" / "security.*",
 * defaults in config/finance.php and config/security.php). Form state uses "__" for dots.
 */
class FinanceSecuritySettings extends Page
{
    use HasPagePermission; // RBAC: page.{slug} + the keys below

    /** @var list<string> */
    protected static array $requiredPermissions = ['settings.manage'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Finance & security';

    /** key => cast (float | int | bool | string) */
    public const KEYS = [
        'finance.gateway_fees.bkash' => 'float', 'finance.gateway_fees.nagad' => 'float', 'finance.gateway_fees.sslcommerz' => 'float',
        'finance.gateway_fees.card' => 'float', 'finance.gateway_fees.cod' => 'float',
        'finance.fx_paid' => 'float', 'finance.usd_bdt' => 'float',
        'finance.skydrop_cost.base_bdt' => 'float', 'finance.skydrop_cost.per_kg_bdt' => 'float', 'finance.unboxing_cost_bdt' => 'float',
        'finance.vat_percent' => 'float', 'finance.vat_on' => 'string',
        'finance.fraud.medium_at' => 'int', 'finance.fraud.high_at' => 'int', 'finance.fraud.require_full_prepay_high' => 'bool',
        'security.admin.require_2fa' => 'bool', 'security.admin.ip_allowlist' => 'string', 'security.admin.session_timeout_minutes' => 'int',
        'security.activity.retention_days' => 'int',
        // Courier delivery history for the fraud check (secrets: write-only, encrypted at rest)
        'finance.fraud.couriers.steadfast.enabled' => 'bool', 'finance.fraud.couriers.steadfast.api_key' => 'string',
        'finance.fraud.couriers.steadfast.secret_key' => 'string',
        'finance.fraud.couriers.pathao.enabled' => 'bool', 'finance.fraud.couriers.pathao.username' => 'string',
        'finance.fraud.couriers.pathao.password' => 'string',
        'finance.fraud.couriers.carrybee.enabled' => 'bool', 'finance.fraud.couriers.carrybee.phone' => 'string',
        'finance.fraud.couriers.carrybee.password' => 'string', 'finance.fraud.couriers.carrybee.business_id' => 'string',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    private static function f(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function mount(): void
    {
        $state = [];
        foreach (self::KEYS as $key => $cast) {
            $state[self::f($key)] = Setting::isSecret($key) ? null : Knobs::get($key); // secrets are never sent to the browser
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $pct = fn (string $k, string $label) => TextInput::make(self::f($k))->label($label)->numeric()->minValue(0)->maxValue(20)->step(0.01)->suffix('%');

        return $schema->statePath('data')->columns(2)->components([
            Section::make('Gateway fees')->description('Percent of each payment, used by the profit report and accounting exports.')->columns(5)->schema([
                $pct('finance.gateway_fees.bkash', 'bKash'), $pct('finance.gateway_fees.nagad', 'Nagad'), $pct('finance.gateway_fees.sslcommerz', 'SSLCommerz'),
                $pct('finance.gateway_fees.card', 'Card'), $pct('finance.gateway_fees.cod', 'COD collection'),
            ])->columnSpanFull(),
            Section::make('Costs')->columns(3)->schema([
                TextInput::make(self::f('finance.fx_paid'))->label('FX actually paid (BDT per INR)')->numeric()->minValue(0)->step(0.0001)
                    ->helperText('0 = use the order FX. Per-day rates (Reports → FX paid) and per-order overrides win.'),
                TextInput::make(self::f('finance.usd_bdt'))->label('USD → BDT (API spend)')->numeric()->minValue(1),
                TextInput::make(self::f('finance.unboxing_cost_bdt'))->label('Unboxing cost per order')->prefix('৳')->numeric()->minValue(0),
                TextInput::make(self::f('finance.skydrop_cost.base_bdt'))->label('Skydrop cost: base')->prefix('৳')->numeric()->minValue(0),
                TextInput::make(self::f('finance.skydrop_cost.per_kg_bdt'))->label('Skydrop cost: per kg')->prefix('৳')->numeric()->minValue(0),
            ]),
            Section::make('VAT / tax (placeholder)')->columns(2)->schema([
                TextInput::make(self::f('finance.vat_percent'))->label('VAT')->numeric()->minValue(0)->maxValue(30)->suffix('%'),
                Select::make(self::f('finance.vat_on'))->label('VAT base')->options(['margin' => 'Service margin', 'revenue' => 'Revenue']),
            ]),
            Section::make('Fraud check')->columns(3)->schema([
                TextInput::make(self::f('finance.fraud.medium_at'))->label('Medium risk from score')->numeric()->minValue(1)->maxValue(100),
                TextInput::make(self::f('finance.fraud.high_at'))->label('High risk from score')->numeric()->minValue(1)->maxValue(100),
                Toggle::make(self::f('finance.fraud.require_full_prepay_high'))->label('High risk: full prepayment instead of 50% advance')->inline(false),
            ]),
            Section::make('Courier history (fraud check)')
                ->description('Each customer phone is checked against these couriers\' delivery records (cached 7 days). Use your merchant accounts. '
                    .'Passwords and keys are encrypted and never shown again; leave blank to keep the saved one.')
                ->headerActions([Action::make('testCouriers')->label('Test logins')->icon(Heroicon::OutlinedSignal)->action(fn () => $this->testCouriers())])
                ->columns(3)->columnSpanFull()->schema([
                    Toggle::make(self::f('finance.fraud.couriers.steadfast.enabled'))->label('Steadfast')->inline(false),
                    $this->secret('finance.fraud.couriers.steadfast.api_key', 'Steadfast API key'),
                    $this->secret('finance.fraud.couriers.steadfast.secret_key', 'Steadfast secret key'),
                    Toggle::make(self::f('finance.fraud.couriers.pathao.enabled'))->label('Pathao')->inline(false),
                    TextInput::make(self::f('finance.fraud.couriers.pathao.username'))->label('Pathao merchant email / phone')->maxLength(120),
                    $this->secret('finance.fraud.couriers.pathao.password', 'Pathao merchant password'),
                    Toggle::make(self::f('finance.fraud.couriers.carrybee.enabled'))->label('CarryBee')->inline(false),
                    TextInput::make(self::f('finance.fraud.couriers.carrybee.phone'))->label('CarryBee merchant phone')->tel()->maxLength(20),
                    $this->secret('finance.fraud.couriers.carrybee.password', 'CarryBee merchant password'),
                    TextInput::make(self::f('finance.fraud.couriers.carrybee.business_id'))->label('CarryBee business id (optional)')->maxLength(40)
                        ->helperText('Only if your account has several businesses.')->columnStart(2),
                ]),
            Section::make('Admin security')->columns(2)->schema([
                Toggle::make(self::f('security.admin.require_2fa'))->label('Require 2FA for admin and manager')->inline(false),
                TextInput::make(self::f('security.admin.session_timeout_minutes'))->label('Idle session timeout')->numeric()->minValue(5)->maxValue(1440)->suffix('min'),
                Textarea::make(self::f('security.admin.ip_allowlist'))->label('Admin IP allowlist')->rows(2)
                    ->helperText(fn () => 'IPs or CIDRs (e.g. 103.10.20.0/24), separated by commas or one per line. Empty = any network. Your current IP is '.request()->ip().'.'),
                TextInput::make(self::f('security.activity.retention_days'))->label('Keep activity log for')->numeric()->minValue(30)->suffix('days'),
            ]),
        ]);
    }

    private function secret(string $key, string $label): TextInput
    {
        $saved = Setting::mask(Knobs::get($key));

        return TextInput::make(self::f($key))->label($label)->password()->revealable()->maxLength(200)->autocomplete('new-password')
            ->placeholder($saved ? 'Saved '.$saved : 'Not set');
    }

    /** Signs in to each enabled courier with a dummy lookup and reports the result (no customer data involved). */
    public function testCouriers(): void
    {
        abort_unless(static::canAccess(), 403);
        Knobs::forget();
        $lines = [];
        foreach (app(FraudService::class)->drivers() as $driver) {
            if (! $driver->enabled()) {
                $lines[] = ucfirst($driver->id()).': off or no login';

                continue;
            }
            try {
                $driver->check('01700000000');
                $lines[] = ucfirst($driver->id()).': OK';
            } catch (Throwable $e) {
                $lines[] = ucfirst($driver->id()).': '.mb_substr($e->getMessage(), 0, 120);
            }
        }
        $ok = ! array_filter($lines, fn ($l) => ! str_ends_with($l, 'OK') && ! str_ends_with($l, 'no login'));
        Notification::make()->title('Courier logins')->body(implode("\n", $lines))->{$ok ? 'success' : 'warning'}()->persistent()->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save')->submit('save')->keyBindings(['mod+s'])])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        $allow = trim((string) ($state[self::f('security.admin.ip_allowlist')] ?? ''));
        if ($allow !== '' && ! IpUtils::checkIp((string) request()->ip(), AdminSecurity::allowlist($allow))) {
            Notification::make()->title('Your current IP ('.request()->ip().') is not in the allowlist; add it first so you are not locked out.')->danger()->send();

            return;
        }
        foreach (self::KEYS as $key => $cast) {
            $v = $state[self::f($key)] ?? null;
            if (Setting::isSecret($key) && ($v === null || $v === '')) {
                continue; // write-only: blank keeps the saved secret
            }
            $v = $v === null || $v === '' ? null : match ($cast) {
                'float' => (float) $v, 'int' => (int) $v, 'bool' => (bool) $v, default => (string) $v,
            };
            Knobs::put($key, $v);
        }
        Notification::make()->title('Saved')->success()->send();
    }
}
