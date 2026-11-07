<?php

namespace App\Filament\Pages;

use App\Filament\Support\Admin;
use App\Services\Commerce\Sms\SmsManager;
use App\Services\Messaging\MessagingSettings as Store;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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

/** Messaging runtime settings (settings table keys "messaging.*", falling back to config/messaging.php / .env). */
class MessagingSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Messaging settings';

    protected static ?string $title = 'Messaging settings';

    /** Editable keys (relative to "messaging."). Secrets are write-only. */
    public const KEYS = [
        'sms.failover', 'sms.bulksmsbd.api_key', 'sms.bulksmsbd.sender_id', 'sms.sslwireless.api_token', 'sms.sslwireless.sid',
        'sms.alpha.api_key', 'sms.alpha.sender_id', 'sms.per_phone_per_hour', 'sms.cost_per_segment_bdt', 'sms.low_balance_bdt',
        'mail.from_address', 'mail.from_name', 'mail.reply_to',
        'reminders.cart_push_after_minutes', 'reminders.cart_followup_after_hours', 'reminders.payment_after_minutes',
        'reminders.review_after_days', 'reminders.points_expiring_days', 'quiet_hours.start', 'quiet_hours.end',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Admin::canManage();
    }

    public function mount(): void
    {
        $state = [];
        foreach (self::KEYS as $key) {
            $v = in_array($key, Store::SECRETS, true) ? null : Store::get($key);
            if (str_starts_with($key, 'quiet_hours.')) {
                $v = Arr::get(Store::get('quiet_hours', config('messaging.quiet_hours')), substr($key, 12));
            }
            if ($key === 'mail.from_address') {
                $v = Store::get($key) ?? config('mail.from.address');
            }
            if ($key === 'mail.from_name') {
                $v = Store::get($key) ?? config('mail.from.name');
            }
            Arr::set($state, $key, $v);
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $secret = fn (string $key, string $label) => TextInput::make($key)->label($label)->password()->revealable()->maxLength(200)
            ->placeholder(fn () => Store::get($key) ? '•••••••• saved (leave blank to keep)' : 'not set');
        $num = fn (string $key, string $label, string $suffix) => TextInput::make($key)->label($label)->numeric()->minValue(0)->suffix($suffix)->required();

        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Section::make('SMS gateways')
                    ->description('Messages try each gateway in order until one accepts. OTP uses OTP_DRIVER first, then this list. Use "log" only in development.')
                    ->columns(2)->columnSpanFull()
                    ->schema([
                        TagsInput::make('sms.failover')->label('Failover order')->columnSpanFull()
                            ->suggestions(array_keys(SmsManager::DRIVERS))->placeholder('bulksmsbd, sslwireless, alpha')
                            ->helperText('Empty = SMS_DRIVER from .env ('.config('retail.sms.driver').').'),
                        $secret('sms.bulksmsbd.api_key', 'BulkSMSBD API key'),
                        TextInput::make('sms.bulksmsbd.sender_id')->label('BulkSMSBD sender id')->maxLength(30)->helperText('Approved sender id / masking from BulkSMSBD.'),
                        $secret('sms.sslwireless.api_token', 'SSL Wireless API token'),
                        TextInput::make('sms.sslwireless.sid')->label('SSL Wireless SID')->maxLength(40),
                        $secret('sms.alpha.api_key', 'Alpha SMS API key (optional)'),
                        TextInput::make('sms.alpha.sender_id')->label('Alpha SMS sender id')->maxLength(30),
                        $num('sms.per_phone_per_hour', 'Max SMS per phone per hour', 'SMS')->helperText('OTP is limited separately (3 per 10 min).'),
                        $num('sms.cost_per_segment_bdt', 'Cost per SMS part (estimate)', '৳'),
                        $num('sms.low_balance_bdt', 'Low balance alert below', '৳'),
                    ]),
                Section::make('Email')
                    ->description('SMTP credentials stay in .env (MAIL_*). Customers only get email when they have added one.')
                    ->columns(3)->columnSpanFull()
                    ->schema([
                        TextInput::make('mail.from_address')->label('From address')->email()->placeholder('orders@yourdomain'),
                        TextInput::make('mail.from_name')->label('From name')->maxLength(80),
                        TextInput::make('mail.reply_to')->label('Reply-to')->email()->placeholder('support@yourdomain'),
                    ]),
                Section::make('Reminders')
                    ->columns(3)->columnSpanFull()
                    ->schema([
                        $num('reminders.cart_push_after_minutes', 'Abandoned cart push after', 'min'),
                        $num('reminders.cart_followup_after_hours', 'Abandoned cart SMS/email after', 'hours'),
                        $num('reminders.payment_after_minutes', 'Unpaid order reminder after', 'min'),
                        $num('reminders.review_after_days', 'Review request after delivery', 'days'),
                        $num('reminders.points_expiring_days', 'Points expiry warning', 'days before'),
                    ]),
                Section::make('Quiet hours (Asia/Dhaka)')
                    ->description('No marketing (abandoned cart, broadcasts) in this window; it is held until the end. Order and payment updates are not affected.')
                    ->columns(2)->columnSpanFull()
                    ->schema([
                        TimePicker::make('quiet_hours.start')->seconds(false)->required(),
                        TimePicker::make('quiet_hours.end')->seconds(false)->required(),
                    ]),
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
                        Action::make('balances')->label('Check SMS balances')->color('gray')->icon(Heroicon::OutlinedBanknotes)->action('checkBalances'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(Admin::canManage(), 403);
        $state = $this->form->getState();
        foreach (self::KEYS as $key) {
            if (str_starts_with($key, 'quiet_hours.')) {
                continue;
            }
            $value = Arr::get($state, $key);
            if (in_array($key, Store::SECRETS, true) && ($value === null || $value === '')) {
                continue; // write-only: blank keeps the saved secret
            }
            if (in_array($key, ['sms.per_phone_per_hour', 'reminders.cart_push_after_minutes', 'reminders.cart_followup_after_hours',
                'reminders.payment_after_minutes', 'reminders.review_after_days', 'reminders.points_expiring_days'], true)) {
                $value = $value === null || $value === '' ? null : (int) $value;
            } elseif (in_array($key, ['sms.cost_per_segment_bdt', 'sms.low_balance_bdt'], true)) {
                $value = $value === null || $value === '' ? null : (float) $value;
            }
            Store::put($key, $value);
        }
        $q = Arr::get($state, 'quiet_hours', []);
        Store::put('quiet_hours', ['start' => substr((string) ($q['start'] ?? '22:00'), 0, 5), 'end' => substr((string) ($q['end'] ?? '09:00'), 0, 5), 'timezone' => 'Asia/Dhaka']);

        Notification::make()->title('Messaging settings saved')->success()->send();
    }

    public function checkBalances(): void
    {
        $b = app(SmsManager::class)->balances();
        $body = $b === [] ? 'No gateway with credentials is configured.' : collect($b)->map(fn ($v, $k) => $k.': '.($v === null ? 'unknown' : number_format($v, 2)))->implode(' · ');
        Notification::make()->title('SMS balances')->body($body)->info()->send();
    }
}
