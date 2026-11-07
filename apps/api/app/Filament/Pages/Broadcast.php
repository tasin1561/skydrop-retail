<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Support\Admin;
use App\Jobs\Messaging\SendBroadcast;
use App\Models\NotificationLog;
use App\Services\Commerce\Notifier;
use App\Services\Messaging\BroadcastAudience;
use App\Services\Messaging\Messenger;
use App\Services\Messaging\SmsSegments;
use App\Support\Features;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/** Marketing campaign to a customer segment over SMS / email / push / in-app. Opt-outs and quiet hours apply. */
class Broadcast extends Page
{
    use HasPagePermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Last dry-run result shown under the form. */
    public ?array $estimate = null;

    /** RBAC: page.{slug} (HasPagePermission) plus these keys. */
    protected static array $requiredPermissions = ['broadcast.send'];

    public static function canSend(): bool
    {
        return method_exists(Admin::class, 'can') ? Admin::can('broadcast.send') : Admin::canManage();
    }

    public function mount(): void
    {
        $this->form->fill(['segment' => 'all', 'days' => 90, 'market' => 'amazon', 'minPoints' => 100, 'channels' => ['push', 'inapp']]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Audience')->columns(4)->schema([
                    Select::make('segment')->options(BroadcastAudience::TYPES)->required()->live()->columnSpan(2),
                    TextInput::make('days')->label('Last N days')->numeric()->minValue(1)->maxValue(730)->visible(fn (Get $get) => $get('segment') === 'recent_buyers'),
                    Select::make('market')->label('Store')->options(Admin::MARKETS)->visible(fn (Get $get) => $get('segment') === 'store'),
                    TextInput::make('minPoints')->label('At least')->suffix('points')->numeric()->minValue(1)->visible(fn (Get $get) => $get('segment') === 'has_points'),
                    CheckboxList::make('channels')->options(['sms' => 'SMS', 'email' => 'Email', 'push' => 'App push', 'inapp' => 'In-app (notification centre)'])
                        ->required()->columns(4)->columnSpanFull()->live()
                        ->helperText('Customers who turned off marketing for a channel are skipped. Nothing marketing goes out 22:00–09:00 (Dhaka); it is held until 09:00.'),
                ]),
                Section::make('Message')->columns(2)->schema([
                    TextInput::make('subject')->label('Title / email subject')->required()->maxLength(120)->columnSpanFull(),
                    Textarea::make('body')->required()->rows(5)->maxLength(1000)->live(debounce: 400)->columnSpanFull()
                        ->helperText(fn (Get $get) => in_array('sms', (array) $get('channels'), true) ? SmsSegments::describe((string) $get('body')).' per SMS' : null),
                    TextInput::make('link')->label('Link (storefront path)')->placeholder('/deals')->maxLength(200)
                        ->helperText('Opened when the push / in-app item is tapped; shown as a button in the email.'),
                ]),
                Html::make(fn () => $this->estimateHtml()),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->footer([
                    Actions::make([
                        Action::make('dryRun')->label('Dry run (count + cost)')->color('gray')->icon(Heroicon::OutlinedCalculator)->action('dryRun'),
                        Action::make('send')->label('Send broadcast')->icon(Heroicon::OutlinedPaperAirplane)
                            ->requiresConfirmation()
                            ->modalDescription(fn () => 'This queues the campaign now. '.($this->estimate ? $this->summary($this->estimate) : 'Run a dry run first to see the reach and SMS cost.'))
                            ->action('send'),
                    ]),
                ]),
            Section::make('Recent broadcasts')->collapsible()->schema([Html::make(fn () => $this->recentHtml())]),
        ]);
    }

    public function dryRun(): void
    {
        $s = $this->form->getState();
        $this->estimate = BroadcastAudience::estimate($this->segment($s), (array) $s['channels'], (string) $s['body']);
        Notification::make()->title('Dry run')->body($this->summary($this->estimate))->info()->send();
    }

    public function send(): void
    {
        abort_unless(self::canSend(), 403);
        if (! Features::enabled('marketing_messages')) {
            Notification::make()->title('Marketing messages are switched off')->body('Turn on "Marketing messages & broadcasts" in System → Features.')->warning()->send();

            return;
        }
        $s = $this->form->getState();
        $id = 'bc_'.now()->format('ymdHi').'_'.Str::lower(Str::random(4));
        $link = trim((string) ($s['link'] ?? '')) ?: null;
        SendBroadcast::dispatch($id, $this->segment($s), (array) $s['channels'], (string) $s['subject'], (string) $s['body'], null, $link);
        $quiet = Messenger::quietUntil();
        Notification::make()->title('Broadcast queued')
            ->body($id.($quiet ? ' · quiet hours: delivery starts '.$quiet->setTimezone('Asia/Dhaka')->format('H:i').' Dhaka' : ''))->success()->send();
        app(Notifier::class)->admin('Broadcast queued', "{$id} by ".(auth()->user()?->email ?? 'staff').': '.$s['subject']);
        $this->estimate = null;
    }

    private function segment(array $s): array
    {
        return ['type' => $s['segment'] ?? 'all', 'days' => $s['days'] ?? null, 'market' => $s['market'] ?? null, 'minPoints' => $s['minPoints'] ?? null];
    }

    private function summary(array $e): string
    {
        return "{$e['users']} customers in segment · SMS {$e['sms']} ({$e['smsSegments']} parts ≈ ৳{$e['smsCostBdt']}) · Email {$e['email']} · Push {$e['push']} · In-app {$e['inapp']}";
    }

    private function estimateHtml(): HtmlString
    {
        if (! $this->estimate) {
            return new HtmlString('');
        }
        $e = $this->estimate;
        $cell = fn ($label, $value) => '<div style="padding:.75rem 1rem;border-radius:.75rem;background:rgba(127,127,127,.08)"><div style="font-size:.75rem;opacity:.7">'.e($label).'</div><div style="font-size:1.25rem;font-weight:700">'.e((string) $value).'</div></div>';

        return new HtmlString('<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(8rem,1fr));gap:.5rem">'
            .$cell('Customers', $e['users']).$cell('SMS', $e['sms']).$cell('SMS parts', $e['smsSegments']).$cell('SMS cost (est.)', '৳'.$e['smsCostBdt'])
            .$cell('Email', $e['email']).$cell('Push', $e['push']).$cell('In-app', $e['inapp']).'</div>');
    }

    private function recentHtml(): HtmlString
    {
        $rows = rescue(fn () => NotificationLog::query()->whereNotNull('broadcast_id')
            ->selectRaw("broadcast_id, min(subject) as subject, min(created_at) as at, sum(case when status = 'sent' then 1 else 0 end) as sent, sum(case when status = 'failed' then 1 else 0 end) as failed, count(*) as total, sum(segments) as parts")
            ->groupBy('broadcast_id')->orderByDesc('at')->limit(8)->get(), collect(), false);
        if ($rows->isEmpty()) {
            return new HtmlString('<p style="opacity:.7;font-size:.875rem">No broadcasts yet.</p>');
        }
        $html = '<table style="width:100%;font-size:.85rem"><tr style="text-align:left;opacity:.7"><th>Broadcast</th><th>Subject</th><th>Sent</th><th>Failed</th><th>Total</th><th>SMS parts</th></tr>';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.e($r->broadcast_id).'</td><td>'.e(Str::limit((string) $r->subject, 40)).'</td><td>'.(int) $r->sent.'</td><td>'.(int) $r->failed.'</td><td>'.(int) $r->total.'</td><td>'.(int) $r->parts.'</td></tr>';
        }

        return new HtmlString($html.'</table>');
    }
}
