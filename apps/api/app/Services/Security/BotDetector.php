<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Heuristics for scrapers. Returns the reasons a request looks automated (empty = looks fine). */
class BotDetector
{
    /** @return array<int, string> */
    public static function headerSignals(Request $request): array
    {
        $ua = (string) $request->userAgent();
        $reasons = [];
        if ($ua === '') {
            $reasons[] = 'no user agent';
        } elseif (preg_match((string) config('security.bot.bad_agents'), $ua)) {
            $reasons[] = 'automation user agent';
        }
        if (str_contains(strtolower((string) $request->header('Sec-CH-UA')), 'headless')) {
            $reasons[] = 'headless browser hint';
        }
        if ($request->hasHeader('X-Webdriver') || $request->hasHeader('X-Selenium')) {
            $reasons[] = 'webdriver header';
        }
        if (! $request->hasHeader('Accept-Language')) {
            $reasons[] = 'missing Accept-Language';
        }

        return $reasons;
    }

    /**
     * Sliding one-minute window of distinct product ids viewed from one IP. Returns how many distinct
     * products this IP opened in the last 60 seconds (including this one).
     */
    public static function trackProduct(string $ip, string $productId): int
    {
        $key = 'bot:products:'.$ip;
        $now = time();
        /** @var array<string, int> $seen */
        $seen = array_filter((array) Cache::get($key, []), fn ($t) => $t > $now - 60);
        $seen[$productId] = $now;
        if (count($seen) > 500) {
            $seen = array_slice($seen, -500, null, true);
        }
        Cache::put($key, $seen, 120);

        return count($seen);
    }

    /** Count of 404s on product pages from one IP in the last minute (id enumeration). */
    public static function trackMiss(string $ip): int
    {
        $key = 'bot:miss:'.$ip.':'.intdiv(time(), 60);
        Cache::add($key, 0, 120);

        return (int) Cache::increment($key);
    }

    public static function flag(string $ip, string $reason): void
    {
        Cache::put('bot:flag:'.$ip, $reason, 3600);
    }

    public static function flagged(string $ip): ?string
    {
        return Cache::get('bot:flag:'.$ip);
    }
}
