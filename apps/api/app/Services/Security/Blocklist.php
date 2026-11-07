<?php

namespace App\Services\Security;

use App\Models\BlockedEntry;
use App\Services\Audit\ActivityLogger;
use App\Services\Commerce\Phone;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Phone / IP / device blocklist. Values are stored encrypted; lookups use a keyed hash. */
class Blocklist
{
    private const CACHE = 'security:blocklist';

    public static function normalise(string $type, string $value): string
    {
        $value = trim($value);

        return match ($type) {
            'phone' => Phone::normalize($value) ?? preg_replace('/\D/', '', $value),
            'ip' => strtolower($value),
            default => $value,
        };
    }

    public static function hash(string $type, string $value): string
    {
        return hash_hmac('sha256', $type.'|'.self::normalise($type, $value), (string) config('app.key'));
    }

    /** @return array<string, true> active "type:hash" keys */
    private static function active(): array
    {
        try {
            return Cache::remember(self::CACHE, 300, fn () => BlockedEntry::query()->active()->get(['type', 'value_hash'])
                ->mapWithKeys(fn ($e) => [$e->type.':'.$e->value_hash => true])->all());
        } catch (Throwable) {
            return [];
        }
    }

    public static function isBlocked(string $type, ?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        return isset(self::active()[$type.':'.self::hash($type, $value)]);
    }

    public static function block(string $type, string $value, string $reason, string $source = 'staff', ?int $hours = null): BlockedEntry
    {
        $hash = self::hash($type, $value);
        $entry = BlockedEntry::query()->firstOrNew(['type' => $type, 'value_hash' => $hash]);
        $entry->fill([
            'value' => self::normalise($type, $value), 'reason' => mb_substr($reason, 0, 250), 'source' => $source,
            'expires_at' => $hours ? now()->addHours($hours) : null,
            'created_by' => $entry->created_by ?? ActivityLogger::actor()?->getAuthIdentifier(),
        ])->save();
        self::flush();

        return $entry;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
    }
}
