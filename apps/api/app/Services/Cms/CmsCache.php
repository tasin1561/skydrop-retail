<?php

namespace App\Services\Cms;

use Illuminate\Support\Facades\Cache;

/** Cache keys for CMS data that the price function reads on every product. Flushed by model events. */
final class CmsCache
{
    public const FLASH = 'cms:flash:v1';

    public const RULES = 'cms:restricted:v1';

    public const TTL = 300;

    /** @var array<string, array{at: float, value: mixed}> per-process memo, so pricing 100 cards reads the cache once */
    private static array $memo = [];

    public static function remember(string $key, \Closure $build): mixed
    {
        $hit = self::$memo[$key] ?? null;
        if ($hit && microtime(true) - $hit['at'] < 5) {
            return $hit['value'];
        }
        try {
            $value = Cache::remember($key, self::TTL, $build);
        } catch (\Throwable) {
            $value = [];
        }
        self::$memo[$key] = ['at' => microtime(true), 'value' => $value];

        return $value;
    }

    public static function flush(): void
    {
        self::$memo = [];
        Cache::forget(self::FLASH);
        Cache::forget(self::RULES);
    }
}
