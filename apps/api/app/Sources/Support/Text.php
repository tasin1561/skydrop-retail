<?php

namespace App\Sources\Support;

/** Small string helpers shared by the parsers. */
final class Text
{
    public static function clean(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Drop invisible direction marks Amazon sprinkles into spec labels.
        $s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', '', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /** "₹1,849.00" -> 1849.0; null when no number. */
    public static function money(mixed $s): ?float
    {
        if (is_int($s) || is_float($s)) {
            return (float) $s;
        }
        if (! is_string($s) || ! preg_match('/(\d[\d,]*(?:\.\d+)?)/', $s, $m)) {
            return null;
        }

        return (float) str_replace(',', '', $m[1]);
    }

    /** "(52,942)" or "52.9K" -> int. */
    public static function count(mixed $s): int
    {
        if (is_int($s) || is_float($s)) {
            return (int) $s;
        }
        if (! is_string($s) || ! preg_match('/(\d[\d,]*(?:\.\d+)?)\s*([KkMmLl]?)/', $s, $m)) {
            return 0;
        }
        $n = (float) str_replace(',', '', $m[1]);

        return (int) round($n * match (strtolower($m[2])) {
            'k' => 1_000, 'l' => 100_000, 'm' => 1_000_000, default => 1,
        });
    }

    public static function label(string $s): string
    {
        $s = rtrim(self::clean($s), " :\u{00A0}");

        return mb_strtoupper(mb_substr($s, 0, 1)).mb_strtolower(mb_substr($s, 1));
    }

    public static function truncate(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max)) : $s;
    }

    public static function isUnavailable(?string $stock): bool
    {
        return (bool) preg_match('/out of stock|unavailable|sold out|not available/i', (string) $stock);
    }
}
