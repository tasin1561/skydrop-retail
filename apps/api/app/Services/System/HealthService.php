<?php

namespace App\Services\System;

use App\Filament\Pages\FeatureSwitches;
use App\Models\ApiLog;
use App\Models\BlockedEntry;
use App\Models\ErrorEvent;
use App\Models\FetchJob;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\ProviderHealth;
use App\Models\User;
use App\Services\Catalog\BudgetGuard;
use App\Services\Commerce\Sms\SmsManager;
use App\Services\Messaging\MessagingSettings;
use App\Support\Features;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Everything System → Health shows, as one JSON-able array (also GET /api/v1/health/details).
 * Each card: {title, status: green|amber|red, summary, items: [{label, value, status?}], ...extra}.
 * Every card is built independently: a failing check turns its own card red instead of breaking the page.
 */
class HealthService
{
    public const RANK = ['green' => 0, 'amber' => 1, 'red' => 2];

    /** @return array{status: string, generatedAt: string, cards: array<string, array<string, mixed>>} */
    public function report(): array
    {
        $cards = [];
        foreach (['app', 'database', 'cache', 'queue', 'providers', 'fetchJobs', 'messaging', 'payments', 'storage', 'security', 'features', 'errors'] as $name) {
            $cards[$name] = $this->safe($name, fn () => $this->{$name}());
        }

        return ['status' => self::worst(array_column($cards, 'status')), 'generatedAt' => now()->toIso8601String(), 'cards' => $cards];
    }

    /** Liveness for load balancers / uptime monitors: DB, cache and (async queues only) a fresh worker heartbeat. */
    public function ready(): array
    {
        $checks = [];
        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
            $checks['database'] = false;
        }
        $checks['cache'] = $this->cacheRoundTrip();
        $checks['queue'] = ! Heartbeat::queueIsAsync() || ($checks['database'] && Heartbeat::fresh(Heartbeat::WORKER));

        return ['ok' => ! in_array(false, $checks, true), 'checks' => $checks, 'time' => now()->toIso8601String()];
    }

    /**
     * Alert conditions for retail:health-check: name => [bad, detail].
     *
     * @return array<string, array{bad: bool, detail: string}>
     */
    public function conditions(): array
    {
        $out = [];
        try {
            DB::select('select 1');
            $out['db_down'] = ['bad' => false, 'detail' => 'Database is reachable again.'];
        } catch (Throwable $e) {
            return ['db_down' => ['bad' => true, 'detail' => 'Database is unreachable: '.Str::limit(LogReader::mask($e->getMessage()), 200)]];
        }

        $stale = (int) config('system.heartbeat_stale_after', 180);
        $age = Heartbeat::age(Heartbeat::WORKER);
        $out['worker_stale'] = Heartbeat::queueIsAsync() && ($age === null || $age > $stale)
            ? ['bad' => true, 'detail' => 'Queue worker heartbeat is '.($age === null ? 'missing' : self::ago($age).' old').'. Is `php artisan queue:work` running?']
            : ['bad' => false, 'detail' => 'Queue worker heartbeat is fresh again.'];

        $failedHour = $this->failedJobsSince(now()->subHour());
        $spike = (int) config('system.alerts.failed_jobs_per_hour', 20);
        $out['failed_jobs_spike'] = ['bad' => $spike > 0 && $failedHour >= $spike, 'detail' => $failedHour >= $spike
            ? "{$failedHour} queue jobs failed in the last hour (alert at {$spike})." : 'Failed jobs are back under the threshold.'];

        $mins = (int) config('system.alerts.circuit_open_minutes', 15);
        $open = ProviderHealth::query()->where('state', 'open')->where('opened_at', '<', now()->subMinutes($mins))->pluck('provider')->all();
        $out['circuit_open'] = ['bad' => $open !== [], 'detail' => $open ? 'Provider circuit open for over '.$mins.' min: '.implode(', ', $open).'.' : 'All provider circuits recovered.'];

        $low = $this->lowSmsBalances(false);
        $out['sms_balance_low'] = ['bad' => $low !== [], 'detail' => $low
            ? 'SMS balance low: '.implode(', ', array_map(fn ($g, $b) => "$g ৳".number_format($b, 2), array_keys($low), $low)).'.' : 'SMS balance is fine again.'];

        $disk = $this->disk();
        $minPct = (float) config('system.alerts.disk_free_pct', 10);
        $out['disk_low'] = ['bad' => $disk['pct'] !== null && $disk['pct'] < $minPct, 'detail' => $disk['pct'] !== null && $disk['pct'] < $minPct
            ? sprintf('Disk free is %.1f%% (%s left).', $disk['pct'], self::bytes($disk['free'])) : 'Disk space is fine again.'];

        $budget = app(BudgetGuard::class);
        $hit = $budget->dailyCapHit();
        $out['daily_cap_hit'] = ['bad' => $hit, 'detail' => $hit
            ? sprintf('Oxylabs daily cap reached ($%.2f of $%.2f). Browsing serves cached data; checkout verification still works.', $budget->spentToday(), $budget->dailyCap())
            : 'Oxylabs daily spend is under the cap again.'];

        return $out;
    }

    // ------------------------------------------------------------------ cards

    private function app(): array
    {
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $deployed = $this->deployedAt();
        $status = $debug && $env === 'production' ? 'red' : ($debug ? 'amber' : 'green');

        return $this->card('App', $status, $env.($debug ? ' · debug ON' : ''), [
            ['Environment', $env],
            ['Debug', $debug ? 'ON' : 'off', $debug ? ($env === 'production' ? 'red' : 'amber') : null],
            ['PHP', PHP_VERSION],
            ['Laravel', app()->version()],
            ['Version', $this->version() ?? '—'],
            ['Deployed', $deployed ? $deployed->toDayDateTimeString().' ('.$deployed->diffForHumans().')' : '—'],
            ['Uptime since deploy', $deployed ? self::ago((int) $deployed->diffInSeconds(now(), true)) : '—'],
            ['Timezone', (string) config('app.timezone')],
        ]);
    }

    private function database(): array
    {
        $t = microtime(true);
        DB::select('select 1');
        $ms = (int) round((microtime(true) - $t) * 1000);
        $driver = DB::connection()->getDriverName();
        [$size, $tables] = Cache::remember('health:db-size', 300, fn () => $this->dbSize());

        return $this->card('Database', $ms > 250 ? 'amber' : 'green', "$driver · {$ms} ms", [
            ['Connection', 'ok · '.$driver.' ('.config('database.default').')'],
            ['Latency', $ms.' ms', $ms > 250 ? 'amber' : null],
            ['Size', $size !== null ? self::bytes($size) : '—'],
        ], ['tables' => $tables]);
    }

    private function cache(): array
    {
        $ok = $this->cacheRoundTrip();

        return $this->card('Cache & sessions', $ok ? 'green' : 'red', $ok ? 'read/write ok' : 'read/write FAILED', [
            ['Cache driver', (string) config('cache.default')],
            ['Read/write test', $ok ? 'ok' : 'failed', $ok ? null : 'red'],
            ['Session driver', (string) config('session.driver')],
            ['Session lifetime', config('session.lifetime').' min'],
        ]);
    }

    private function queue(): array
    {
        $conn = (string) config('queue.default');
        $async = Heartbeat::queueIsAsync();
        $stale = (int) config('system.heartbeat_stale_after', 180);
        $workerAge = Heartbeat::age(Heartbeat::WORKER);
        $schedAge = Heartbeat::age(Heartbeat::SCHEDULER);
        $workerBad = $async && ($workerAge === null || $workerAge > $stale);
        $schedBad = $schedAge === null || $schedAge > $stale;

        $pending = [];
        $oldest = null;
        if ($conn === 'database') {
            $table = (string) config('queue.connections.database.table', 'jobs');
            foreach (DB::table($table)->selectRaw('queue, count(*) as n, min(created_at) as oldest')->groupBy('queue')->get() as $r) {
                $pending[$r->queue] = (int) $r->n;
                $oldest = $oldest === null ? (int) $r->oldest : min($oldest, (int) $r->oldest);
            }
        } elseif ($async) {
            foreach (array_unique(['default', ...array_values((array) config('messaging.queue', []))]) as $q) {
                $pending[$q] = rescue(fn () => Queue::size($q), 0, false);
            }
        }
        $oldestAge = $oldest ? max(0, now()->getTimestamp() - $oldest) : null;
        $failed24 = $this->failedJobsSince(now()->subDay());
        $failedHour = $this->failedJobsSince(now()->subHour());
        $spike = (int) config('system.alerts.failed_jobs_per_hour', 20);

        $status = ($workerBad || $schedBad || ($spike > 0 && $failedHour >= $spike)) ? 'red'
            : (($failed24 > 0 || ($oldestAge !== null && $oldestAge > 300)) ? 'amber' : 'green');

        $recent = [];
        if (Schema::hasTable('failed_jobs')) {
            foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(10)->get(['id', 'uuid', 'queue', 'payload', 'exception', 'failed_at']) as $f) {
                $payload = json_decode((string) $f->payload, true);
                $recent[] = ['id' => $f->id, 'uuid' => $f->uuid, 'queue' => $f->queue, 'job' => class_basename((string) ($payload['displayName'] ?? '?')),
                    'error' => Str::limit(LogReader::mask(strtok((string) $f->exception, "\n") ?: ''), 220), 'failedAt' => (string) $f->failed_at];
            }
        }

        return $this->card('Queue', $status, $async ? array_sum($pending).' pending · '.$failed24.' failed (24h)' : 'sync (jobs run inline)', [
            ['Connection', $conn],
            ['Pending', $pending ? implode(', ', array_map(fn ($q, $n) => "$q: $n", array_keys($pending), $pending)) : '0'],
            ['Oldest pending', $oldestAge !== null ? self::ago($oldestAge) : '—', $oldestAge !== null && $oldestAge > 300 ? 'amber' : null],
            ['Failed (1h / 24h)', $failedHour.' / '.$failed24, $failed24 ? ($failedHour >= $spike && $spike > 0 ? 'red' : 'amber') : null],
            ['Worker heartbeat', ! $async ? 'not needed (sync)' : ($workerAge === null ? 'never seen' : self::ago($workerAge).' ago'), $workerBad ? 'red' : null],
            ['Scheduler heartbeat', $schedAge === null ? 'never seen' : self::ago($schedAge).' ago', $schedBad ? 'red' : null],
        ], ['failed' => $recent, 'help' => 'Run `php artisan queue:work --tries=3` (Supervisor/systemd) and `php artisan schedule:work` (or a cron calling schedule:run every minute).']);
    }

    private function providers(): array
    {
        $stats = Cache::remember('health:providers', 60, fn () => $this->providerStats());
        $budget = app(BudgetGuard::class);
        $today = $budget->spentToday();
        $month = $budget->spent();
        $monthly = $budget->budget();
        $daily = $budget->dailyCap();
        $elapsedDays = max(1 / 24, now()->startOfMonth()->diffInSeconds(now(), true) / 86400);
        $projected = $month / $elapsedDays * now()->daysInMonth;

        $circuits = [];
        $openLong = false;
        $openAny = false;
        foreach (ProviderHealth::query()->orderBy('provider')->get() as $h) {
            $mins = (int) config('system.alerts.circuit_open_minutes', 15);
            $long = $h->state === 'open' && $h->opened_at && $h->opened_at->lt(now()->subMinutes($mins));
            $openLong = $openLong || $long;
            $openAny = $openAny || $h->state !== 'closed';
            $circuits[$h->provider] = ['state' => $h->state, 'failCount' => (int) $h->fail_count,
                'openedAt' => $h->opened_at?->toIso8601String(), 'lastSuccessAt' => $h->last_success_at?->toIso8601String(),
                'status' => $h->state === 'closed' ? 'green' : ($long ? 'red' : 'amber')];
        }
        foreach (array_keys($stats['providers']) as $key) {
            $circuits[$key] ??= ['state' => 'closed', 'failCount' => 0, 'openedAt' => null, 'lastSuccessAt' => null, 'status' => 'green'];
        }
        foreach ($circuits as $key => $c) {
            $circuits[$key] += ['stats' => $stats['providers'][$key] ?? null];
        }
        ksort($circuits);

        $dailyHit = $daily > 0 && $today >= $daily;
        $monthlyPct = $monthly > 0 ? $month / $monthly * 100 : 0;
        $status = ($openLong || $dailyHit || $monthlyPct >= 100) ? 'red'
            : (($openAny || $monthlyPct >= 90 || ($monthly > 0 && $projected > $monthly)) ? 'amber' : 'green');

        return $this->card('Data providers', $status, sprintf('$%.2f today · $%.2f this month', $today, $month), [
            ['Oxylabs today', sprintf('$%.2f / $%s daily cap', $today, $daily > 0 ? number_format($daily, 2) : '∞'), $dailyHit ? 'red' : null],
            ['Oxylabs this month', sprintf('$%.2f / $%.2f (%d%%)', $month, $monthly, (int) $monthlyPct), $monthlyPct >= 100 ? 'red' : ($monthlyPct >= 90 ? 'amber' : null)],
            ['Projected month-end', sprintf('$%.2f', $projected), $monthly > 0 && $projected > $monthly ? 'amber' : null],
            ['Cache hit rate (24h)', $stats['cacheHitRate'] !== null ? $stats['cacheHitRate'].'%' : '—'],
        ], ['circuits' => $circuits, 'spend' => ['todayUsd' => round($today, 4), 'monthUsd' => round($month, 4), 'dailyCapUsd' => $daily,
            'monthlyBudgetUsd' => $monthly, 'projectedUsd' => round($projected, 2)]]);
    }

    private function fetchJobs(): array
    {
        $since = now()->subHour();
        $counts = FetchJob::query()->where('updated_at', '>=', $since)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $durations = FetchJob::query()->where('status', 'done')->where('updated_at', '>=', $since)->latest('updated_at')->limit(2000)
            ->get(['created_at', 'updated_at'])->map(fn ($j) => $j->created_at->diffInSeconds($j->updated_at, true));
        $avg = $durations->count() ? round($durations->avg(), 1) : null;
        $failed = (int) ($counts['failed'] ?? 0);
        $done = (int) ($counts['done'] ?? 0);
        $status = ($failed >= 5 && $failed > $done) ? 'red' : ($failed > 0 ? 'amber' : 'green');

        return $this->card('Fetch jobs (1h)', $status, $done.' done · '.$failed.' failed', [
            ['Queued', (int) ($counts['queued'] ?? 0)],
            ['Running', (int) ($counts['running'] ?? 0)],
            ['Done', $done],
            ['Failed', $failed, $failed ? $status : null],
            ['Avg time to done', $avg !== null ? $avg.' s' : '—'],
        ]);
    }

    private function messaging(): array
    {
        $balances = $this->smsBalances(false);
        $lowAt = (float) MessagingSettings::get('sms.low_balance_bdt', 200);
        $low = array_filter($balances['values'], fn ($b) => $b !== null && $b < $lowAt);
        $rows = NotificationLog::query()->where('created_at', '>=', now()->subDay())
            ->selectRaw('channel, status, count(*) as n')->groupBy('channel', 'status')->get();
        $c = fn (string $ch, string $st) => (int) ($rows->first(fn ($r) => $r->channel === $ch && $r->status === $st)->n ?? 0);
        $failed = $c('sms', 'failed') + $c('email', 'failed') + $c('push', 'failed');
        $status = $low ? 'red' : ($failed > 0 ? 'amber' : 'green');

        $items = [];
        foreach ($balances['values'] as $gw => $b) {
            $items[] = ['SMS balance · '.$gw, $b === null ? 'unknown' : '৳'.number_format($b, 2), isset($low[$gw]) ? 'red' : null];
        }
        if (! $balances['values']) {
            $items[] = ['SMS balance', 'no gateway with a balance API ('.implode(', ', app(SmsManager::class)->chain()).')'];
        }
        $items[] = ['Balance checked', $balances['at'] ? Carbon::parse($balances['at'])->diffForHumans() : 'never'];
        $items[] = ['SMS sent / failed (24h)', $c('sms', 'sent').' / '.$c('sms', 'failed'), $c('sms', 'failed') ? 'amber' : null];
        $items[] = ['Email sent / failed (24h)', $c('email', 'sent').' / '.$c('email', 'failed'), $c('email', 'failed') ? 'amber' : null];
        $items[] = ['Push sent / failed (24h)', $c('push', 'sent').' / '.$c('push', 'failed'), $c('push', 'failed') ? 'amber' : null];

        return $this->card('Messaging', $status, $low ? 'SMS balance low' : $failed.' failed (24h)', $items, ['lowBalanceBdt' => $lowAt]);
    }

    private function payments(): array
    {
        $mode = (string) config('retail.payments.mode', 'mock');
        $prod = config('app.env') === 'production';
        $p = (array) config('retail.payments');
        $gateways = [
            'bKash' => ! empty($p['bkash']['app_key']) && ! empty($p['bkash']['app_secret']),
            'Nagad' => ! empty($p['nagad']['merchant_id']),
            'SSLCommerz' => ! empty($p['sslcommerz']['store_id']),
        ];
        $failed = Payment::query()->where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count();
        $settled = Payment::query()->whereIn('status', ['paid', 'success', 'completed'])->where('updated_at', '>=', now()->subDay())->count();
        $webhooks = Schema::hasTable('skydrop_events') ? DB::table('skydrop_events')->where('at', '>=', now()->subDay())->count() : 0;
        $modeBad = $prod && $mode !== 'live';
        $status = $modeBad ? 'red' : ($failed > 0 ? 'amber' : 'green');

        $items = [['Mode', $mode, $modeBad ? 'red' : ($mode !== 'live' ? 'amber' : null)]];
        foreach ($gateways as $name => $ok) {
            $items[] = [$name, $mode === 'mock' ? 'mock' : ($ok ? $mode : 'not configured'), $mode !== 'mock' && ! $ok ? 'amber' : null];
        }
        $items[] = ['Payments paid (24h)', $settled];
        $items[] = ['Payments failed (24h)', $failed, $failed ? 'amber' : null];
        $items[] = ['Skydrop webhooks (24h)', $webhooks];

        return $this->card('Payments', $status, $mode.' · '.$failed.' failed (24h)', $items);
    }

    private function storage(): array
    {
        $disk = $this->disk();
        $sizes = Cache::remember('health:storage-sizes', 600, fn () => [
            'public' => self::dirSize(storage_path('app/public')),
            'raw' => Schema::hasTable('raw_pages') ? (int) DB::table('raw_pages')->selectRaw('coalesce(sum(length(html_gz)), 0) as b')->value('b') : 0,
        ]);
        $logs = LogReader::totalBytes();
        $minPct = (float) config('system.alerts.disk_free_pct', 10);
        $status = $disk['pct'] === null ? 'amber' : ($disk['pct'] < $minPct ? 'red' : ($disk['pct'] < $minPct * 2 ? 'amber' : 'green'));

        return $this->card('Storage', $status, $disk['pct'] !== null ? sprintf('%.1f%% free', $disk['pct']) : 'disk unknown', [
            ['Disk free', $disk['pct'] !== null ? sprintf('%s of %s (%.1f%%)', self::bytes($disk['free']), self::bytes($disk['total']), $disk['pct']) : '—', $status !== 'green' ? $status : null],
            ['Public uploads', self::bytes($sizes['public'])],
            ['raw_pages (HTML cache)', self::bytes($sizes['raw'])],
            ['Log files', self::bytes($logs)],
        ]);
    }

    private function security(): array
    {
        $ips = BlockedEntry::query()->active()->where('type', 'ip')->count();
        $honeypot = DB::table('activity_log')->where('action', 'security.honeypot')->where('created_at', '>=', now()->subDay())->count();
        $staff = User::query()->where('is_admin', true)->whereNull('blocked_at');
        $total = (clone $staff)->count();
        $with = Schema::hasColumn('users', 'app_authentication_secret') ? (clone $staff)->whereNotNull('app_authentication_secret')->count() : 0;
        $without = $total - $with;

        return $this->card('Security', $without > 0 ? 'amber' : 'green', $ips.' IPs blocked', [
            ['Blocked IPs (active)', $ips],
            ['Honeypot hits (24h)', $honeypot, $honeypot ? 'amber' : null],
            ['429 responses (24h)', HealthStats::total429()],
            ['Staff with 2FA', $with.' of '.$total],
            ['Staff without 2FA', $without, $without ? 'amber' : null],
            ['Request guards', config('security.enforce') ? 'enforced' : 'OFF', config('security.enforce') ? null : 'amber'],
        ]);
    }

    private function features(): array
    {
        $all = Features::all();
        $on = count(array_filter($all));
        $m = Maintenance::state();
        $checkoutOff = ! ($all['checkout'] ?? true);
        $url = rescue(fn () => FeatureSwitches::getUrl(), null, false);

        return $this->card('Feature switches', ($m['on'] || $checkoutOff) ? 'amber' : 'green', $on.' on · '.(count($all) - $on).' off', [
            ['On', $on],
            ['Off', count($all) - $on],
            ['Checkout', $checkoutOff ? 'OFF' : 'on', $checkoutOff ? 'amber' : null],
            ['Maintenance mode', $m['on'] ? 'ON' : 'off', $m['on'] ? 'amber' : null],
        ], ['link' => $url]);
    }

    private function errors(): array
    {
        $recent = ErrorEvent::query()->orderByDesc('last_seen_at')->limit(20)->get();
        $day = (int) ErrorEvent::query()->where('last_seen_at', '>=', now()->subDay())->count();
        $hour = (int) ErrorEvent::query()->where('last_seen_at', '>=', now()->subHour())->sum('count');
        $status = $hour >= 50 ? 'red' : ($day > 0 ? 'amber' : 'green');

        return $this->card('Errors', $status, $day.' distinct (24h)', [
            ['Distinct errors (24h)', $day, $day ? 'amber' : null],
            ['Occurrences seen in the last hour (rows)', $hour],
        ], ['events' => $recent->map(fn (ErrorEvent $e) => [
            'id' => $e->id, 'class' => $e->class, 'message' => Str::limit($e->message, 300), 'where' => $e->file.':'.$e->line,
            'url' => $e->url, 'count' => $e->count, 'firstSeen' => $e->first_seen_at?->toIso8601String(), 'lastSeen' => $e->last_seen_at?->toIso8601String(),
        ])->values()->all()]);
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{values: array<string, ?float>, at: ?string} cached SMS gateway balances (refresh = call the gateways) */
    public function smsBalances(bool $refresh): array
    {
        if ($refresh) {
            Cache::forget('health:sms-balance');
        }

        return Cache::remember('health:sms-balance', 1800, function () {
            $values = rescue(fn () => app(SmsManager::class)->balances(), [], false);

            return ['values' => array_map(fn ($v) => $v === null ? null : (float) $v, (array) $values), 'at' => now()->toIso8601String()];
        });
    }

    /** @return array<string, float> gateways under the low-balance threshold */
    private function lowSmsBalances(bool $refresh): array
    {
        $lowAt = (float) MessagingSettings::get('sms.low_balance_bdt', 200);

        return array_filter($this->smsBalances($refresh)['values'], fn ($b) => $b !== null && $b < $lowAt);
    }

    public function failedJobsSince(Carbon $since): int
    {
        try {
            return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at', '>=', $since)->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array{free: ?float, total: ?float, pct: ?float} */
    public function disk(): array
    {
        $free = @disk_free_space(storage_path()) ?: null;
        $total = @disk_total_space(storage_path()) ?: null;

        return ['free' => $free, 'total' => $total, 'pct' => $free !== null && $total ? $free / $total * 100 : null];
    }

    private function cacheRoundTrip(): bool
    {
        try {
            $key = 'health:probe:'.Str::random(8);
            $val = Str::random(16);
            Cache::put($key, $val, 30);
            $ok = Cache::get($key) === $val;
            Cache::forget($key);

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{0: ?int, 1: list<array{name: string, size: ?int, rows?: ?int}>} */
    private function dbSize(): array
    {
        $driver = DB::connection()->getDriverName();
        $size = null;
        if ($driver === 'sqlite') {
            $path = (string) config('database.connections.sqlite.database');
            $size = $path !== ':memory:' && is_file($path) ? (int) filesize($path) : null;
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $size = (int) DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())
                ->selectRaw('coalesce(sum(data_length + index_length), 0) as b')->value('b');
        }
        $tables = [];
        try {
            foreach (Schema::getTables() as $t) {
                $tables[] = ['name' => (string) $t['name'], 'size' => isset($t['size']) ? (int) $t['size'] : null];
            }
        } catch (Throwable) {
        }
        if ($tables && collect($tables)->every(fn ($t) => ! $t['size'])) {
            foreach ($tables as $i => $t) { // no size info (SQLite without dbstat): rank by rows
                $tables[$i]['rows'] = rescue(fn () => DB::table($t['name'])->count(), null, false);
            }
            usort($tables, fn ($a, $b) => ($b['rows'] ?? 0) <=> ($a['rows'] ?? 0));
        } else {
            usort($tables, fn ($a, $b) => ($b['size'] ?? 0) <=> ($a['size'] ?? 0));
        }
        if ($size === null && $tables && ! collect($tables)->every(fn ($t) => ! $t['size'])) {
            $size = (int) array_sum(array_column($tables, 'size'));
        }

        return [$size, array_slice($tables, 0, 8)];
    }

    /** @return array{providers: array<string, array<string, mixed>>, cacheHitRate: ?float} */
    private function providerStats(): array
    {
        $rows = ApiLog::query()->where('created_at', '>=', now()->subDay())->where('provider', '!=', 'cache')
            ->latest('id')->limit(50000)->get(['provider', 'market', 'status', 'ms', 'created_at']);
        $hourAgo = now()->subHour();
        $out = [];
        foreach ($rows->groupBy(fn ($r) => $r->provider.':'.($r->market ?? '-')) as $key => $list) {
            $window = function ($set) {
                $n = $set->count();
                $ms = $set->pluck('ms')->map(fn ($v) => (int) $v)->sort()->values();

                return ['calls' => $n, 'successPct' => $n ? round($set->filter(fn ($r) => $r->status >= 200 && $r->status < 400)->count() / $n * 100, 1) : null,
                    'p50' => $n ? $ms[(int) floor(($n - 1) * 0.5)] : null, 'p95' => $n ? $ms[(int) floor(($n - 1) * 0.95)] : null];
            };
            $out[$key] = ['hour' => $window($list->filter(fn ($r) => $r->created_at >= $hourAgo)), 'day' => $window($list)];
        }
        $hits = ApiLog::query()->where('created_at', '>=', now()->subDay())->where('provider', 'cache')->count();
        $total = $hits + $rows->count();

        return ['providers' => $out, 'cacheHitRate' => $total ? round($hits / $total * 100, 1) : null];
    }

    private function deployedAt(): ?Carbon
    {
        if ($v = config('system.deployed_at')) {
            return rescue(fn () => Carbon::parse($v), null, false);
        }
        foreach ([base_path('bootstrap/cache/config.php'), base_path('bootstrap/cache/packages.php'), base_path('vendor/autoload.php')] as $f) {
            if (is_file($f)) {
                return Carbon::createFromTimestamp((int) filemtime($f));
            }
        }

        return null;
    }

    /** APP_VERSION, else the git short sha (read from .git without running git). */
    public function version(): ?string
    {
        if ($v = config('system.version')) {
            return (string) $v;
        }

        return Cache::remember('health:version', 300, function () {
            foreach ([base_path('.git'), base_path('../.git'), base_path('../../.git')] as $git) {
                if (! is_file($git.'/HEAD')) {
                    continue;
                }
                $head = trim((string) file_get_contents($git.'/HEAD'));
                if (! str_starts_with($head, 'ref: ')) {
                    return substr($head, 0, 7);
                }
                $ref = substr($head, 5);
                if (is_file($git.'/'.$ref)) {
                    return substr(trim((string) file_get_contents($git.'/'.$ref)), 0, 7);
                }
                if (is_file($git.'/packed-refs') && preg_match('/^([0-9a-f]{40}) '.preg_quote($ref, '/').'$/m', (string) file_get_contents($git.'/packed-refs'), $m)) {
                    return substr($m[1], 0, 7);
                }
            }

            return null;
        });
    }

    private static function dirSize(string $dir, int $maxFiles = 50000): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $bytes = 0;
        $n = 0;
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $bytes += $file->isFile() ? $file->getSize() : 0;
                if (++$n >= $maxFiles) {
                    break;
                }
            }
        } catch (Throwable) {
        }

        return $bytes;
    }

    /** @param  list<array{0: string, 1: mixed, 2?: ?string}>  $items */
    private function card(string $title, string $status, string $summary, array $items, array $extra = []): array
    {
        return ['title' => $title, 'status' => $status, 'summary' => $summary,
            'items' => array_map(fn ($i) => ['label' => $i[0], 'value' => $i[1], 'status' => $i[2] ?? null], $items)] + $extra;
    }

    private function safe(string $name, Closure $build): array
    {
        try {
            return $build();
        } catch (Throwable $e) {
            return ['title' => Str::headline($name), 'status' => 'red', 'summary' => 'check failed',
                'items' => [['label' => 'Error', 'value' => Str::limit(LogReader::mask($e->getMessage()), 300), 'status' => 'red']]];
        }
    }

    /** @param  list<string>  $statuses */
    public static function worst(array $statuses): string
    {
        $worst = 'green';
        foreach ($statuses as $s) {
            if ((self::RANK[$s] ?? 0) > self::RANK[$worst]) {
                $worst = $s;
            }
        }

        return $worst;
    }

    public static function bytes(int|float|null $b): string
    {
        if ($b === null) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }

        return round($b, $i ? 1 : 0).' '.$units[$i];
    }

    public static function ago(int $seconds): string
    {
        return match (true) {
            $seconds < 90 => $seconds.' s',
            $seconds < 5400 => round($seconds / 60).' min',
            $seconds < 172800 => round($seconds / 3600, 1).' h',
            default => round($seconds / 86400, 1).' days',
        };
    }
}
