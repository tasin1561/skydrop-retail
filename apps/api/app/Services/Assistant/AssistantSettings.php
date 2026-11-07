<?php

namespace App\Services\Assistant;

use App\Models\Assistant\AssistantMessage;
use App\Models\Setting;
use App\Support\Features;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Assistant runtime settings: settings table key "assistant.{key}" first, then config/assistant.php.
 * On/off is the central feature switch "ai_assistant" (App\Support\Features).
 */
class AssistantSettings
{
    /** Keys edited on Admin → AI assistant. */
    public const KEYS = [
        'model', 'fallback_model', 'fallback_at_pct', 'monthly_budget_usd', 'limits.guest', 'limits.user', 'limits.plus',
        'greeting.en', 'greeting.bn', 'prompts.en', 'prompts.bn', 'holidays',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = config('assistant.'.$key, $default);
        try {
            $v = Cache::remember('assistant-setting:'.$key, 60, fn () => ['v' => Setting::get('assistant.'.$key)])['v'];
        } catch (Throwable) {
            return $fallback;
        }

        return $v === null || $v === '' ? $fallback : $v;
    }

    public static function put(string $key, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            Setting::query()->whereKey('assistant.'.$key)->delete();
        } else {
            Setting::put('assistant.'.$key, $value);
        }
        Cache::forget('assistant-setting:'.$key);
    }

    public static function enabled(): bool
    {
        return class_exists(Features::class) ? Features::enabled('ai_assistant') : true;
    }

    public static function hasKey(): bool
    {
        return (string) config('assistant.api_key') !== '';
    }

    public static function budget(): float
    {
        return (float) self::get('monthly_budget_usd', 50);
    }

    public static function spentThisMonth(): float
    {
        try {
            return (float) AssistantMessage::query()->where('created_at', '>=', now()->startOfMonth())->sum('cost_usd');
        } catch (Throwable) {
            return 0.0;
        }
    }

    public static function overBudget(): bool
    {
        $budget = self::budget();

        return $budget <= 0 || self::spentThisMonth() >= $budget;
    }

    /** Admin-chosen model, or the cheaper fallback once fallback_at_pct of the budget is spent. */
    public static function model(): string
    {
        $model = (string) self::get('model');
        $pct = (float) self::get('fallback_at_pct', 80);
        $budget = self::budget();
        if ($pct > 0 && $budget > 0 && self::spentThisMonth() / $budget * 100 >= $pct) {
            return self::fallbackModel();
        }

        return $model;
    }

    public static function fallbackModel(): string
    {
        return (string) self::get('fallback_model');
    }

    /** @return list<string> */
    public static function prompts(string $locale): array
    {
        $v = self::get('prompts.'.$locale, []);
        if (is_string($v)) {
            $v = preg_split('/\r?\n/', $v) ?: [];
        }

        return array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) $v)));
    }

    /** @return list<array{name: string, date: string, note?: string}> */
    public static function holidays(): array
    {
        $v = self::get('holidays', []);
        if (is_string($v)) {
            // Admin textarea: one per line "YYYY-MM-DD | Name | optional note"
            $rows = [];
            foreach (preg_split('/\r?\n/', $v) ?: [] as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[0] ?? '') && ($parts[1] ?? '') !== '') {
                    $rows[] = array_filter(['date' => $parts[0], 'name' => $parts[1], 'note' => $parts[2] ?? null]);
                }
            }

            return $rows;
        }

        return array_values(array_filter((array) $v, fn ($h) => is_array($h) && isset($h['date'], $h['name'])));
    }

    /** USD cost of one API call's usage. */
    public static function cost(string $model, int $in, int $out, int $cacheRead = 0, int $cacheWrite = 0): float
    {
        $prices = (array) config('assistant.prices');
        $p = $prices[$model] ?? $prices['*'] ?? ['in' => 3, 'out' => 15];

        return round(($in * $p['in'] + $cacheWrite * $p['in'] * 1.25 + $cacheRead * $p['in'] * 0.1 + $out * $p['out']) / 1_000_000, 6);
    }
}
