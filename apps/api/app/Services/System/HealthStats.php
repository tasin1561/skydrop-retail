<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Throwable;

/** Cheap hourly counters kept in the cache (429 responses). 25 hourly buckets give a rolling 24 h total. */
class HealthStats
{
    public static function count429(): void
    {
        try {
            $key = 'health:429:'.now()->format('YmdH');
            Cache::add($key, 0, 90000);
            Cache::increment($key);
        } catch (Throwable) {
        }
    }

    public static function total429(int $hours = 24): int
    {
        $n = 0;
        try {
            for ($h = 0; $h < $hours; $h++) {
                $n += (int) Cache::get('health:429:'.now()->subHours($h)->format('YmdH'), 0);
            }
        } catch (Throwable) {
        }

        return $n;
    }
}
