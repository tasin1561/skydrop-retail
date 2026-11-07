<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Ops alerts: Telegram when ALERT_TELEGRAM_BOT/CHAT are set, otherwise the log. Never throws. */
class Alerts
{
    public function send(string $message, string $level = 'warning'): void
    {
        Log::log($level, '[alert] '.$message);
        $bot = config('retail.alerts.telegram_bot');
        $chat = config('retail.alerts.telegram_chat');
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
