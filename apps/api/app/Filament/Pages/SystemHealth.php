<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Models\ErrorEvent;
use App\Models\ProviderHealth;
use App\Services\Audit\ActivityLogger;
use App\Services\System\HealthService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * System → Health: status cards (green / amber / red) for app, database, cache, queue, providers, fetch jobs,
 * messaging, payments, storage, security, features and errors. Polls every 30 s. Same data as GET /health/details.
 * Needs page.system-health + system.health.
 */
class SystemHealth extends Page
{
    use HasPagePermission;

    /** @var list<string> */
    protected static array $requiredPermissions = ['system.health'];

    protected static ?string $slug = 'system-health';

    protected static ?string $title = 'Health';

    protected static ?string $navigationLabel = 'Health';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.system.health';

    /** @return array{status: string, generatedAt: string, cards: array<string, array<string, mixed>>} */
    public function getReport(): array
    {
        return app(HealthService::class)->report();
    }

    public function retryJob(string $uuid): void
    {
        $this->guard();
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        ActivityLogger::log('system.job_retry', null, [], 'Retried failed job '.$uuid);
        Notification::make()->title('Job pushed back onto the queue')->success()->send();
    }

    public function forgetJob(string $uuid): void
    {
        $this->guard();
        Artisan::call('queue:forget', ['id' => $uuid]);
        ActivityLogger::log('system.job_forget', null, [], 'Deleted failed job '.$uuid);
        Notification::make()->title('Failed job deleted')->success()->send();
    }

    public function resetCircuit(string $key): void
    {
        $this->guard();
        ProviderHealth::query()->whereKey($key)->update(['state' => 'closed', 'fail_count' => 0, 'opened_at' => null, 'updated_at' => now()]);
        Cache::forget('health:providers');
        ActivityLogger::log('system.circuit_reset', null, [], 'Reset provider circuit '.$key);
        Notification::make()->title("Circuit $key closed")->success()->send();
    }

    public function refreshSms(): void
    {
        $this->guard();
        $b = app(HealthService::class)->smsBalances(true);
        Notification::make()->title($b['values'] ? 'SMS balance refreshed' : 'No gateway with a balance API')->success()->send();
    }

    public function clearErrors(): void
    {
        $this->guard();
        $n = ErrorEvent::query()->delete();
        ActivityLogger::log('system.errors_cleared', null, [], "Cleared $n error groups");
        Notification::make()->title("Cleared $n error groups")->success()->send();
    }

    private function guard(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
