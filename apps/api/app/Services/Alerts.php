<?php

namespace App\Services;

use App\Services\Messaging\Messenger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Ops alerts: Telegram when ALERT_TELEGRAM_BOT/CHAT are set, otherwise the log. Never throws. */
class Alerts
{
    public function send(string $message, string $level = 'warning'): void
    {
        Log::log($level, '[alert] '.$message);
        // Messaging module: also email ALERT_EMAIL (queued; no-op when unset).
        if (config('retail.alerts.email')) {
            rescue(fn () => app(Messenger::class)->admin(ucfirst($level).' alert', $message), null, false);
        }
        $bot = config('retail.alerts.telegram_bot');
        $chat = \App\Services\Catalog\RetailSettings::get('alerts.telegram_chat'); // Admin → Settings override, else ALERT_TELEGRAM_CHAT
        if (! $bot || ! $chat) {
            return;
        }
        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$bot}/sendMessage", [
                'chat_id' => $chat, 'text' => "Skydrop Retail: $message", 'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $e) {
            Log::warning('Telegram alert failed: '.$e->getMessage());
        }
    }
}
