<?php

namespace App\Providers;

use App\Jobs\System\WorkerHeartbeat;
use App\Services\System\ErrorRecorder;
use App\Services\System\Heartbeat;
use App\Services\System\LogReader;
use App\Support\Knobs;
use App\Support\Rbac\PermissionRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * System module: limits, health, alerts, maintenance (Admin → System → Limits / Health / Settings).
 * Registers the permission keys, the heartbeat + health-check schedules and the error_events reporter.
 */
class SystemServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PermissionRegistry::extend([
            'system.limits' => 'Edit system limits',
            'system.health' => 'View system health (retry failed jobs, reset circuits)',
            'system.settings' => 'Maintenance mode, cache clear, queue restart, logs',
        ], 'System');

        // Group reported exceptions into error_events (throttled; never throws).
        $this->callAfterResolving(ExceptionHandler::class, function ($handler) {
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (Throwable $e) {
                    ErrorRecorder::record($e);
                });
            }
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Heartbeats: the scheduler writes its own every minute and queues one for the worker.
            $schedule->call(fn () => Heartbeat::beat(Heartbeat::SCHEDULER))->everyMinute()->name('system:scheduler-heartbeat');
            $schedule->job(new WorkerHeartbeat)->everyMinute()->name('system:worker-heartbeat')->onOneServer();
            $schedule->command('retail:health-check')->everyFiveMinutes()->name('system:health-check')->onOneServer()->withoutOverlapping(10);
            $schedule->call(fn () => LogReader::prune((int) Knobs::get('system.log_retention_days', 14)))->dailyAt('03:55')->name('system:log-retention');
        });
    }
}
