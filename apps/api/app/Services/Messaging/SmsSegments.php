<?php

namespace App\Services\Messaging;

/**
 * SMS length maths. GSM-7: 160 chars in one part, 153 per part when concatenated (extension-table characters
 * count twice). Anything outside GSM-7 (e.g. Bangla) is UCS-2: 70 / 67 UTF-16 units.
 */
final class SmsSegments
{
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const GSM_EXT = "^{}\\[~]|€\f";

    public static function isUnicode(string $text): bool
    {
        foreach (mb_str_split($text) as $ch) {
            if (! str_contains(self::GSM_BASIC, $ch) && ! str_contains(self::GSM_EXT, $ch)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{encoding: 'GSM-7'|'UCS-2', length: int, segments: int, perSegment: int, remaining: int} */
    public static function analyze(string $text): array
    {
        if (self::isUnicode($text)) {
            $len = intdiv(strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);
            [$single, $multi, $enc] = [70, 67, 'UCS-2'];
        } else {
            $len = 0;
            foreach (mb_str_split($text) as $ch) {
                $len += str_contains(self::GSM_EXT, $ch) ? 2 : 1;
            }
            [$single, $multi, $enc] = [160, 153, 'GSM-7'];
        }
        $segments = $len === 0 ? 0 : ($len <= $single ? 1 : (int) ceil($len / $multi));
        $per = $segments > 1 ? $multi : $single;

        return ['encoding' => $enc, 'length' => $len, 'segments' => $segments, 'perSegment' => $per, 'remaining' => max(0, $per * max(1, $segments) - $len)];
    }

    public static function count(string $text): int
    {
        return self::analyze($text)['segments'];
    }

    public static function describe(string $text): string
    {
        $a = self::analyze($text);

        return "{$a['length']} chars · {$a['encoding']} · {$a['segments']} segment".($a['segments'] === 1 ? '' : 's')." · {$a['remaining']} left";
    }
}
