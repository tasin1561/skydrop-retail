<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Models\Address;
use App\Models\ApiLog;
use App\Models\Assistant\AssistantMessage;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\ProductWatch;
use App\Models\WishlistItem;
use App\Services\Assistant\AssistantSettings;
use App\Services\Audit\ActivityLogger;
use App\Services\Catalog\BudgetGuard;
use App\Support\Limits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * System → Limits: every entry of App\Support\Limits::REGISTRY, grouped, with units, help, current usage and
 * reset-to-default. Limits owned by another module write that module's own setting (one source of truth).
 * Needs page.limits + system.limits.
 */
class SystemLimits extends Page
{
    use HasPagePermission;

    /** @var list<string> */
    protected static array $requiredPermissions = ['system.limits'];

    protected static ?string $slug = 'limits';

    protected static ?string $title = 'Limits';

    protected static ?string $navigationLabel = 'Limits';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.system.limits';

    public ?array $data = [];

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function mount(): void
    {
        Limits::flush();
        $this->form->fill($this->state());
    }

    /** @return array<string, int|float> */
    private function state(): array
    {
        $out = [];
        foreach (Limits::all() as $key => $v) {
            $out[self::field($key)] = $v;
        }

        return $out;
    }

    public function form(Schema $schema): Schema
    {
        $usage = $this->usage();
        $sections = [];
        foreach (Limits::groups() as $group => $limits) {
            $fields = [];
            foreach ($limits as $key => $m) {
                $default = $m['default'].($m['unit'] !== '' ? ' '.$m['unit'] : '');
                $help = trim($m['help'].' Default: '.$default.'. Range '.$m['min'].'–'.$m['max'].'.'.($m['backed'] ? ' (setting "'.$m['backed'].'")' : ''));
                $fields[] = TextInput::make(self::field($key))
                    ->label($m['label'])
                    ->numeric()->required()
                    ->minValue($m['min'])->maxValue($m['max'])
                    ->step(is_float($m['default']) || in_array($m['unit'], ['USD'], true) ? 0.01 : 1)
                    ->suffix($m['unit'] ?: null)
                    ->hint($usage[$key] ?? null)
                    ->hintColor('info')
                    ->helperText($help)
                    ->suffixAction(Action::make('reset_'.Str::slug($key, '_'))
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->tooltip('Reset to default ('.$default.')')
                        ->action(fn () => $this->resetLimit($key)));
            }
            $sections[] = Section::make($group)->columns(2)->schema($fields)->collapsible();
        }

        return $schema->statePath('data')->components($sections);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        $before = Limits::all();
        $changes = [];
        foreach (array_keys(Limits::REGISTRY) as $key) {
            $v = $state[self::field($key)] ?? null;
            if ($v === null || $v === '' || (float) $v == (float) $before[$key]) {
                continue;
            }
            Limits::set($key, $v);
            $changes[$key] = [$before[$key], Limits::get($key)];
        }
        if ($changes) {
            ActivityLogger::log('limits.updated', null, $changes, 'System limits: '.implode(', ', array_keys($changes)));
        }
        $this->form->fill($this->state());
        Notification::make()->title($changes ? count($changes).' limit'.(count($changes) > 1 ? 's' : '').' saved' : 'Nothing changed')->success()->send();
    }

    public function resetLimit(string $key): void
    {
        abort_unless(static::canAccess() && isset(Limits::REGISTRY[$key]), 403);
        $old = Limits::get($key);
        Limits::reset($key);
        $new = Limits::get($key);
        if ($old != $new) {
            ActivityLogger::log('limits.reset', null, [$key => [$old, $new]], 'System limit reset to default: '.$key);
        }
        $this->data[self::field($key)] = $new;
        Notification::make()->title(Limits::REGISTRY[$key][0].' reset to '.$new)->success()->send();
    }

    /** @return array<string, string> key => "current usage" hint */
    public function usage(): array
    {
        $u = [];
        $try = function (string $key, callable $fn) use (&$u) {
            try {
                $u[$key] = (string) $fn();
            } catch (Throwable) {
                // missing table / module: no hint
            }
        };
        $budget = app(BudgetGuard::class);
        $today = now()->startOfDay();
        $try('catalog.oxylabs_daily_usd', fn () => sprintf('Today: $%.2f', $budget->spentToday()));
        $try('catalog.oxylabs_monthly_usd', fn () => sprintf('This month: $%.2f', $budget->spent()));
        $try('catalog.store_daily_fetches', function () use ($today) {
            $rows = ApiLog::query()->where('provider', '!=', 'cache')->where('created_at', '>=', $today)->whereNotNull('market')
                ->selectRaw('market, count(*) as n')->groupBy('market')->pluck('n', 'market');

            return 'Today: '.($rows->isEmpty() ? '0' : $rows->map(fn ($n, $m) => "$m $n")->implode(' · '));
        });
        $try('orders.max_per_day', function () use ($today) {
            $q = Order::query()->where('kind', 'goods')->where('created_at', '>=', $today);
            $top = (clone $q)->selectRaw('user_id, count(*) as n')->groupBy('user_id')->orderByDesc('n')->value('n');

            return 'Orders today: '.$q->count().' · busiest customer: '.(int) $top;
        });
        $try('orders.max_unpaid', fn () => 'Most unpaid by one customer: '.(int) Order::query()->where('kind', 'goods')->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['pending', 'failed'])->selectRaw('user_id, count(*) as n')->groupBy('user_id')->orderByDesc('n')->value('n'));
        $try('orders.max_value_bdt', fn () => 'Largest order today: ৳'.number_format((float) Order::query()->where('created_at', '>=', $today)->max('total_bdt')));
        $try('accounts.max_addresses', fn () => 'Most by one customer: '.(int) Address::query()->selectRaw('user_id, count(*) as n')->groupBy('user_id')->orderByDesc('n')->value('n'));
        $try('accounts.max_wishlist', fn () => 'Most by one customer: '.(int) WishlistItem::query()->selectRaw('user_id, count(*) as n')->groupBy('user_id')->orderByDesc('n')->value('n'));
        $try('accounts.max_price_alerts', fn () => 'Most by one customer: '.(int) ProductWatch::query()->where('active', true)->selectRaw('user_id, count(*) as n')->groupBy('user_id')->orderByDesc('n')->value('n'));
        $try('messaging.marketing_sms_per_day', fn () => 'Sent today: '.NotificationLog::query()->where('channel', 'sms')->where('category', 'marketing')
            ->where('status', 'sent')->where('sent_at', '>=', $today)->count());
        $try('assistant.monthly_budget_usd', fn () => sprintf('This month: $%.2f', AssistantSettings::spentThisMonth()));
        $try('assistant.user_per_day', fn () => 'Messages today (all): '.AssistantMessage::query()->where('role', 'user')->where('created_at', '>=', $today)->count());

        return $u;
    }
}
