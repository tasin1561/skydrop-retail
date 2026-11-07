<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Staff activity log. Usage from any module:
 *
 *   ActivityLogger::log('roles.updated', $role, ['permissions' => [$old, $new]], 'Changed manager permissions');
 *   ActivityLogger::log('export', null, [], 'Profit CSV', ['from' => '2026-11-01']);
 *
 * Model create/update/delete is logged automatically for the models registered in OpsServiceProvider
 * (or any model using App\Services\Audit\LogsActivity); Filament actions are logged by a Livewire hook.
 * Never throws: logging must not break the action being logged.
 */
class ActivityLogger
{
    /** Field names whose values are never stored. */
    public const REDACT = ['password', 'remember_token', 'token', 'secret', 'api_key', 'access_token', 'app_authentication_secret',
        'app_authentication_recovery_codes', 'code_hash', 'private_key', 'store_password', 'app_secret', 'key'];

    private static bool $paused = false;

    /**
     * @param  array<string, mixed>  $changes  {field: [old, new]} or free-form data (redacted)
     * @param  array<string, mixed>  $meta
     */
    public static function log(string $action, ?Model $subject = null, array $changes = [], ?string $description = null, array $meta = [], ?Authenticatable $actor = null): ?ActivityLog
    {
        if (self::$paused) {
            return null;
        }
        try {
            $actor ??= self::actor();
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            return ActivityLog::query()->create([
                'user_id' => $actor?->getAuthIdentifier(),
                'user_name' => $actor ? Str::limit((string) ($actor->name ?? $actor->email ?? ''), 117) : null,
                'action' => Str::limit($action, 57, ''),
                'subject_type' => $subject ? $subject::class : ($meta['subject_type'] ?? null),
                'subject_id' => $subject ? (string) $subject->getKey() : (isset($meta['subject_id']) ? (string) $meta['subject_id'] : null),
                'description' => $description ? Str::limit($description, 497) : null,
                'changes' => $changes ? self::redact($changes) : null,
                'meta' => $meta ? self::redact($meta) : null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 252) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('[activity] could not log '.$action.': '.$e->getMessage());

            return null;
        }
    }

    /** Signed-in staff member (web guard, used by Filament), or null. */
    public static function actor(): ?Authenticatable
    {
        try {
            $user = Auth::guard('web')->user();
        } catch (Throwable) {
            return null;
        }

        return $user;
    }

    public static function isStaff(?Authenticatable $user = null): bool
    {
        $user ??= self::actor();

        return (bool) ($user?->is_admin ?? false);
    }

    /** Run without logging (bulk maintenance, seeding). */
    public static function quietly(callable $fn): mixed
    {
        $prev = self::$paused;
        self::$paused = true;
        try {
            return $fn();
        } finally {
            self::$paused = $prev;
        }
    }

    /** @param  array<string|int, mixed>  $data */
    public static function redact(array $data, int $depth = 0): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $lk = strtolower((string) $k);
            if (is_string($k) && (in_array($lk, self::REDACT, true) || str_ends_with($lk, '_secret') || str_ends_with($lk, '_token') || str_contains($lk, 'password'))) {
                $out[$k] = '[redacted]';
            } elseif (is_array($v)) {
                $out[$k] = $depth > 4 ? '[…]' : self::redact($v, $depth + 1);
            } elseif (is_string($v) && mb_strlen($v) > 2000) {
                $out[$k] = mb_substr($v, 0, 2000).'…';
            } elseif (is_object($v)) {
                $out[$k] = method_exists($v, '__toString') ? (string) $v : get_class($v);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
