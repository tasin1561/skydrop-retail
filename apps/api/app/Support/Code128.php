<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Minimal Code 128 (code set B) encoder that renders an inline SVG. Enough for order ids, box codes and AWBs
 * (printable ASCII 32–126). Each symbol is 11 modules; the stop symbol is 13.
 */
final class Code128
{
    /** Bar/space widths for symbol values 0–106 (103–105 = start A/B/C, 106 = stop). */
    public const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /** @return array<int, int> symbol values including start, checksum and stop */
    public static function values(string $text): array
    {
        if ($text === '' || preg_match('/[^\x20-\x7E]/', $text)) {
            throw new InvalidArgumentException('Code 128B supports printable ASCII only.');
        }
        $values = [self::START_B];
        $sum = self::START_B;
        foreach (str_split($text) as $i => $ch) {
            $v = ord($ch) - 32;
            $values[] = $v;
            $sum += $v * ($i + 1);
        }
        $values[] = $sum % 103;
        $values[] = self::STOP;

        return $values;
    }

    /** Module widths, alternating bar/space, starting with a bar. */
    public static function widths(string $text): array
    {
        $out = [];
        foreach (self::values($text) as $v) {
            foreach (str_split(self::PATTERNS[$v]) as $w) {
                $out[] = (int) $w;
            }
        }

        return $out;
    }

    /** Inline SVG (black bars on white, 10-module quiet zones). */
    public static function svg(string $text, int $height = 60, float $module = 2.0, bool $withText = true): string
    {
        $quiet = 10;
        $x = $quiet;
        $rects = '';
        foreach (self::widths($text) as $i => $w) {
            if ($i % 2 === 0) {
                $rects .= '<rect x="'.($x * $module).'" y="0" width="'.($w * $module).'" height="'.$height.'"/>';
            }
            $x += $w;
        }
        $width = ($x + $quiet) * $module;
        $textH = $withText ? 18 : 0;
        $label = $withText
            ? '<text x="'.($width / 2).'" y="'.($height + 15).'" text-anchor="middle" font-family="monospace" font-size="14" fill="#000">'.e($text).'</text>'
            : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Barcode '.e($text).'" width="'.$width.'" height="'.($height + $textH).'" viewBox="0 0 '.$width.' '.($height + $textH).'">'
            .'<rect width="100%" height="100%" fill="#fff"/><g fill="#000">'.$rects.'</g>'.$label.'</svg>';
    }
}
