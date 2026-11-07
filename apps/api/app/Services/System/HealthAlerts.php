<?php

namespace App\Services\System;

use App\Services\Catalog\RetailSettings;
use App\Services\Commerce\Notifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * retail:health-check (every 5 minutes). Each condition alerts once when it turns red and once when it recovers
 * (deduped by a small state file, so it works even when the database or cache is the thing that is down).
 * Channels: Notifier::admin (email to ALERT_EMAIL, else the log) and Telegram (ALERT_TELEGRAM_BOT + the
 * "alerts.telegram_chat" setting / ALERT_TELEGRAM_CHAT).
 */
class HealthAlerts
{
    public const LABELS = [
        'db_down' => 'Database down', 'worker_stale' => 'Queue worker stopped', 'failed_jobs_spike' => 'Failed jobs spike',
        'circuit_open' => 'Provider circuit open', 'sms_balance_low' => 'SMS balance low', 'disk_low' => 'Disk almost full',
        'daily_cap_hit' => 'Oxylabs daily cap reached',
    ];

    public function __construct(private HealthService $health) {}

    /** @return array{alerts: list<string>, recoveries: list<string>, state: array<string, bool>} */
    public function run(): array
    {
        $state = $this->state();
        $alerts = [];
        $recoveries = [];
        foreach ($this->health->conditions() as $name => ['bad' => $bad, 'detail' => $detail]) {
            $was = (bool) ($state[$name] ?? false);
            $label = self::LABELS[$name] ?? $name;
            if ($bad && ! $was) {
                $this->send("🔴 $label", $detail);
                $alerts[] = $name;
            } elseif (! $bad && $was) {
                $this->send("✅ Recovered: $label", $detail);
                $recoveries[] = $name;
            }
            $state[$name] = $bad;
        }
        $this->save($state);

        return ['alerts' => $alerts, 'recoveries' => $recoveries, 'state' => $state];
    }

    public function send(string $subject, string $text): void
    {
        try {
            app(Notifier::class)->admin($subject, $text);
        } catch (Throwable $e) {
            Log::warning('[health] admin alert failed: '.$e->getMessage());
        }
        $bot = config('retail.alerts.telegram_bot');
        $chat = RetailSettings::get('alerts.telegram_chat');
        if (! $bot || ! $chat) {
            Log::warning("[health] $subject: $text");

            return;
        }
        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$bot}/sendMessage", [
                'chat_id' => $chat, 'text' => "Skydrop Retail — $subject\n$text", 'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $e) {
            Log::warning('[health] Telegram alert failed: '.$e->getMessage());
        }
    }

    public static function stateFile(): string
    {
        return storage_path('framework/health-alerts.json');
    }

    /** @return array<string, bool> */
    public function state(): array
    {
        $raw = @file_get_contents(self::stateFile());

        return is_string($raw) ? (array) json_decode($raw, true) : [];
    }

    /** @param  array<string, bool>  $state */
    private function save(array $state): void
    {
        @file_put_contents(self::stateFile(), json_encode($state), LOCK_EX);
    }
}
