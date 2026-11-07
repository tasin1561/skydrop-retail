<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Groups reported exceptions into error_events by fingerprint (class + file + line). Throttled: at most one write per
 * fingerprint per minute; occurrences in between are counted in the cache and added on the next write.
 * Registered as a reportable callback in SystemServiceProvider. Never throws.
 */
class ErrorRecorder
{
    private static bool $busy = false;

    public static function fingerprint(Throwable $e): string
    {
        return sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine());
    }

    public static function record(Throwable $e): void
    {
        if (self::$busy) {
            return;
        }
        self::$busy = true;
        try {
            $fp = self::fingerprint($e);
            $key = 'error_events:'.$fp;
            if (! Cache::add($key, 1, 60)) {
                Cache::add($key.':n', 0, 3600);
                Cache::increment($key.':n');

                return;
            }
            $extra = (int) Cache::pull($key.':n', 0);
            $now = now();
            $url = app()->runningInConsole() ? 'console: '.implode(' ', array_slice($_SERVER['argv'] ?? [], 0, 4)) : request()->fullUrl();
            $row = [
                'class' => Str::limit(get_class($e), 187, ''),
                'message' => Str::limit(LogReader::mask($e->getMessage()), 2000),
                'file' => Str::limit(str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()), 297, ''),
                'line' => $e->getLine(),
                'url' => Str::limit(LogReader::mask($url), 497, ''),
                'last_seen_at' => $now,
            ];
            $updated = DB::table('error_events')->where('fingerprint', $fp)
                ->update($row + ['count' => DB::raw('count + '.(1 + $extra))]);
            if (! $updated) {
                DB::table('error_events')->insert($row + ['fingerprint' => $fp, 'count' => 1 + $extra, 'first_seen_at' => $now]);
            }
        } catch (Throwable) {
            // table missing / DB down: nothing to record into
        } finally {
            self::$busy = false;
        }
    }
}
