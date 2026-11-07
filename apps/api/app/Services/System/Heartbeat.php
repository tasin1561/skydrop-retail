<?php

namespace App\Services\System;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Worker and scheduler heartbeats (system_heartbeats). The scheduler writes "scheduler" every minute and queues
 * App\Jobs\System\WorkerHeartbeat, which writes "worker" when a queue worker picks it up.
 */
class Heartbeat
{
    public const WORKER = 'worker';

    public const SCHEDULER = 'scheduler';

    public static function beat(string $name, array $meta = []): void
    {
        try {
            DB::table('system_heartbeats')->updateOrInsert(['name' => $name], ['beat_at' => now(), 'meta' => $meta ? json_encode($meta) : null]);
        } catch (Throwable) {
            // never break the worker / scheduler
        }
    }

    public static function at(string $name): ?Carbon
    {
        try {
            $v = DB::table('system_heartbeats')->where('name', $name)->value('beat_at');
        } catch (Throwable) {
            return null;
        }

        return $v ? Carbon::parse($v) : null;
    }

    /** Seconds since the last beat, or null when it never beat. */
    public static function age(string $name): ?int
    {
        $at = self::at($name);

        return $at ? (int) max(0, $at->diffInSeconds(now(), true)) : null;
    }

    public static function fresh(string $name): bool
    {
        $age = self::age($name);

        return $age !== null && $age <= (int) config('system.heartbeat_stale_after', 180);
    }

    /** A worker heartbeat only matters when jobs go through a real queue (not "sync"). */
    public static function queueIsAsync(): bool
    {
        return ! in_array(config('queue.default'), ['sync', 'null'], true);
    }
}
