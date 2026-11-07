<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Models\Marketplace;
use App\Models\SearchCache;
use App\Models\Setting;
use App\Services\Audit\ActivityLogger;
use App\Services\Cms\CmsCache;
use App\Services\System\LogReader;
use App\Services\System\Maintenance;
use App\Support\Features;
use App\Support\Knobs;
use App\Support\Limits;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * System → Settings (maintenance): storefront maintenance mode with a message (web + app via GET /features),
 * cache clear buttons, queue restart signal, log retention and a read-only log viewer (secrets masked).
 * Needs page.system-settings + system.settings.
 */
class SystemSettings extends Page
{
    use HasPagePermission;

    /** @var list<string> */
    protected static array $requiredPermissions = ['system.settings'];

    protected static ?string $slug = 'system-settings';

    protected static ?string $title = 'Settings (maintenance)';

    protected static ?string $navigationLabel = 'Settings (maintenance)';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.system.settings';

    public ?array $data = [];

    public int $logLines = 200;

    public function mount(): void
    {
        $m = Maintenance::state();
        $this->form->fill([
            'maintenance_on' => $m['on'],
            'maintenance_message' => (string) (((array) Setting::get('system.maintenance', []))['message'] ?? ''),
            'log_retention_days' => (int) Knobs::get('system.log_retention_days', 14),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Maintenance mode')->description('Shoppers on the website and in the app see a full-page "We\'ll be right back" screen with this message. The API, admin and webhooks keep working.')
                ->schema([
                    Toggle::make('maintenance_on')->label('Storefront in maintenance')->inline(false),
                    Textarea::make('maintenance_message')->label('Message')->rows(3)->maxLength(500)->placeholder(Maintenance::DEFAULT_MESSAGE),
                ]),
            Section::make('Logs')->schema([
                TextInput::make('log_retention_days')->label('Keep log files for')->numeric()->minValue(1)->maxValue(365)->suffix('days')->required()
                    ->helperText('storage/logs/*.log older than this are deleted daily (03:55).'),
            ]),
        ]);
    }

    public function save(): void
    {
        $this->guard();
        $s = $this->form->getState();
        $before = Maintenance::state();
        Maintenance::set((bool) $s['maintenance_on'], (string) ($s['maintenance_message'] ?? ''));
        Knobs::put('system.log_retention_days', (int) $s['log_retention_days']);
        if ($before['on'] !== (bool) $s['maintenance_on']) {
            ActivityLogger::log('system.maintenance', null, ['on' => [$before['on'], (bool) $s['maintenance_on']]],
                'Maintenance mode '.($s['maintenance_on'] ? 'ON' : 'OFF'));
        }
        Notification::make()->title('Saved'.((bool) $s['maintenance_on'] ? ' · storefront is in maintenance' : ''))->success()->send();
    }

    public function clearCache(string $what): void
    {
        $this->guard();
        $msg = match ($what) {
            'catalog' => $this->clearCatalog(),
            'settings' => $this->clearSettings(),
            'features' => $this->clearFeatures(),
            default => abort(404),
        };
        ActivityLogger::log('system.cache_clear', null, [], 'Cleared '.$what.' cache');
        Notification::make()->title($msg)->success()->send();
    }

    public function restartQueue(): void
    {
        $this->guard();
        Artisan::call('queue:restart');
        ActivityLogger::log('system.queue_restart', null, [], 'Queue restart signal');
        Notification::make()->title('Workers will restart after their current job')->body('Supervisor / systemd must bring them back up.')->success()->send();
    }

    /** @return array{file: ?string, lines: list<string>} */
    public function getLog(): array
    {
        return LogReader::tail(max(20, min(1000, $this->logLines)));
    }

    private function clearCatalog(): string
    {
        $n = SearchCache::query()->delete(); // cached store search pages; products are kept
        foreach (Marketplace::query()->pluck('id') as $m) {
            Cache::forget("catalog:live:$m");
        }
        CmsCache::flush();
        Cache::forget('health:providers');

        return "Catalog cache cleared ($n cached search pages)";
    }

    private function clearSettings(): string
    {
        foreach (Setting::query()->pluck('key') as $key) {
            Cache::forget("retail-setting:$key");
            Cache::forget("setting:$key");
            if (str_starts_with($key, 'assistant.')) {
                Cache::forget('assistant-setting:'.substr($key, 10));
            }
        }
        Knobs::forget();
        Limits::flush();
        Cache::forget('system:maintenance');

        return 'Settings cache cleared';
    }

    private function clearFeatures(): string
    {
        Features::flush();

        return 'Feature switches reloaded';
    }

    private function guard(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
