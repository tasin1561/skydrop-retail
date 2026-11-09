<?php

namespace App\Filament\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/** Dependency-free inline SVG charts for report pages (light/dark friendly: uses currentColor for text). */
class SvgChart
{
    private const POS = '#5b3df5';

    private const NEG = '#e11d48';

    private const MUTED = 'rgba(127,127,127,.35)';

    /**
     * Horizontal bars, one per label, revenue (light) behind profit (solid).
     *
     * @param  array<string, array{revenue: float, profit: float}>  $data
     */
    public static function bars(array $data, string $title): HtmlString
    {
        if (! $data) {
            return new HtmlString('<p style="opacity:.6;font-size:.85rem">No data for '.e($title).'.</p>');
        }
        uasort($data, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $data = array_slice($data, 0, 12, true);
        $max = max(1, max(array_map(fn ($d) => max(abs($d['revenue']), abs($d['profit'])), $data)));
        $row = 26;
        $labelW = 120;
        $w = 520;
        $h = count($data) * $row + 8;
        $svg = '<svg role="img" aria-label="'.e($title).'" viewBox="0 0 '.$w.' '.$h.'" width="100%" style="max-width:100%;font-size:11px;color:inherit">';
        $y = 4;
        foreach ($data as $label => $d) {
            $rw = ($w - $labelW - 70) * abs($d['revenue']) / $max;
            $pw = ($w - $labelW - 70) * abs($d['profit']) / $max;
            $svg .= '<text x="0" y="'.($y + 15).'" fill="currentColor">'.e(mb_strimwidth((string) $label, 0, 18, '…')).'</text>';
            $svg .= '<rect x="'.$labelW.'" y="'.($y + 3).'" width="'.round($rw, 1).'" height="16" rx="3" fill="'.self::MUTED.'"><title>Revenue ৳'.number_format($d['revenue']).'</title></rect>';
            $svg .= '<rect x="'.$labelW.'" y="'.($y + 7).'" width="'.round($pw, 1).'" height="8" rx="2" fill="'.($d['profit'] < 0 ? self::NEG : self::POS).'"><title>Profit ৳'.number_format($d['profit']).'</title></rect>';
            $svg .= '<text x="'.($w - 4).'" y="'.($y + 15).'" text-anchor="end" fill="currentColor">৳'.e(self::short($d['profit'])).'</text>';
            $y += $row;
        }

        return new HtmlString($svg.'</svg>');
    }

    /**
     * Daily profit columns (negative days below the axis) with a revenue line.
     *
     * @param  array<string, array{revenue: float, profit: float}>  $days
     */
    public static function daily(array $days): HtmlString
    {
        if (! $days) {
            return new HtmlString('<p style="opacity:.6;font-size:.85rem">No orders in this range.</p>');
        }
        $w = 720;
        $h = 180;
        $pad = 24;
        $n = count($days);
        $max = max(1, max(array_map(fn ($d) => max(abs($d['profit']), $d['revenue']), $days)));
        $minP = min(0, min(array_column($days, 'profit')));
        $scale = ($h - 2 * $pad) / ($max - $minP);
        $zero = $h - $pad + $minP * $scale;
        $bw = max(2, ($w - 2 * $pad) / $n - 2);
        $svg = '<svg role="img" aria-label="Profit by day" viewBox="0 0 '.$w.' '.$h.'" width="100%" style="font-size:10px;color:inherit">';
        $svg .= '<line x1="'.$pad.'" x2="'.($w - $pad).'" y1="'.$zero.'" y2="'.$zero.'" stroke="'.self::MUTED.'"/>';
        $i = 0;
        $points = [];
        foreach ($days as $day => $d) {
            $x = $pad + $i * (($w - 2 * $pad) / $n);
            $ph = abs($d['profit']) * $scale;
            $y = $d['profit'] >= 0 ? $zero - $ph : $zero;
            $svg .= '<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.round($bw, 1).'" height="'.round(max(1, $ph), 1).'" fill="'.($d['profit'] < 0 ? self::NEG : self::POS).'"><title>'.e($day).': profit ৳'.number_format($d['profit']).', revenue ৳'.number_format($d['revenue']).'</title></rect>';
            $points[] = round($x + $bw / 2, 1).','.round($zero - $d['revenue'] * $scale, 1);
            if ($n <= 16 || $i % (int) ceil($n / 12) === 0) {
                $svg .= '<text x="'.round($x, 1).'" y="'.($h - 6).'" fill="currentColor">'.e(substr($day, 5)).'</text>';
            }
            $i++;
        }
        $svg .= '<polyline fill="none" stroke="'.self::MUTED.'" stroke-width="2" points="'.implode(' ', $points).'"><title>Revenue</title></polyline>';

        return new HtmlString($svg.'</svg>');
    }

    /**
     * Product data usage per day: successful Amazon (bottom) and Flipkart (top) calls stacked, failed calls as a line.
     *
     * @param  array<string, array{amazon: int, flipkart: int, failed: int}>  $days  Y-m-d => counts
     * @param  ?string  $until  last day with data (Y-m-d); the failed line stops there
     */
    public static function usageDaily(array $days, ?string $until = null): HtmlString
    {
        if (! $days || ! array_filter($days, fn ($d) => $d['amazon'] + $d['flipkart'] + $d['failed'] > 0)) {
            return new HtmlString('<p style="opacity:.6;font-size:.85rem">No product data calls in this cycle yet.</p>');
        }
        $w = 760;
        $h = 220;
        $left = 44;
        $right = 12;
        $top = 12;
        $bottom = 26;
        $n = count($days);
        $max = max(1, max(array_map(fn ($d) => max($d['amazon'] + $d['flipkart'], $d['failed']), $days)));
        $max = max(4, self::niceMax($max));
        $plotH = $h - $top - $bottom;
        $slot = ($w - $left - $right) / $n;
        $bw = max(2, $slot * 0.72);
        $y = fn (float $v) => round($top + $plotH - $v / $max * $plotH, 1);

        $svg = '<svg role="img" aria-label="Product data calls per day" viewBox="0 0 '.$w.' '.$h.'" width="100%" style="font-size:10px;color:inherit">';
        foreach ([0, 0.5, 1] as $f) {
            $gy = $y($max * $f);
            $svg .= '<line x1="'.$left.'" x2="'.($w - $right).'" y1="'.$gy.'" y2="'.$gy.'" stroke="'.self::MUTED.'" stroke-width="'.($f === 0 ? 1 : 0.5).'"/>';
            $svg .= '<text x="'.($left - 6).'" y="'.($gy + 3).'" text-anchor="end" fill="currentColor" opacity=".7">'.e(self::short($max * $f)).'</text>';
        }
        $i = 0;
        $points = [];
        foreach ($days as $day => $d) {
            $x = $left + $i * $slot + ($slot - $bw) / 2;
            $label = Carbon::parse($day)->format('j M');
            $tip = e($label.': Amazon India '.number_format($d['amazon']).', Flipkart '.number_format($d['flipkart']).', failed '.number_format($d['failed']));
            $ya = $y($d['amazon']);
            $yf = $y($d['amazon'] + $d['flipkart']);
            if ($d['amazon'] > 0) {
                $svg .= '<rect x="'.round($x, 1).'" y="'.$ya.'" width="'.round($bw, 1).'" height="'.round($top + $plotH - $ya, 1).'" fill="'.self::AMAZON.'"><title>'.$tip.'</title></rect>';
            }
            if ($d['flipkart'] > 0) {
                $svg .= '<rect x="'.round($x, 1).'" y="'.$yf.'" width="'.round($bw, 1).'" height="'.round($ya - $yf, 1).'" fill="'.self::FLIPKART.'"><title>'.$tip.'</title></rect>';
            }
            if ($until === null || $day <= $until) {
                $points[] = round($x + $bw / 2, 1).','.$y($d['failed']);
            }
            if ($n <= 16 || $i % (int) ceil($n / 10) === 0) {
                $svg .= '<text x="'.round($x + $bw / 2, 1).'" y="'.($h - 8).'" text-anchor="middle" fill="currentColor" opacity=".7">'.e($label).'</text>';
            }
            $i++;
        }
        $svg .= '<polyline fill="none" stroke="'.self::NEG.'" stroke-width="2" stroke-linejoin="round" points="'.implode(' ', $points).'"><title>Failed calls (not billed)</title></polyline>';

        return new HtmlString($svg.'</svg>');
    }

    private const AMAZON = '#5b3df5';

    private const FLIPKART = '#0ea5e9';

    /** @return array<string, string> series => colour, for the legend */
    public static function usageColours(): array
    {
        return ['amazon' => self::AMAZON, 'flipkart' => self::FLIPKART, 'failed' => self::NEG];
    }

    private static function niceMax(float $v): float
    {
        $mag = 10 ** floor(log10(max(1, $v)));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($v <= $m * $mag) {
                return $m * $mag;
            }
        }

        return 10 * $mag;
    }

    public static function short(float $v): string
    {
        $a = abs($v);
        $s = $a >= 1e6 ? round($a / 1e6, 1).'M' : ($a >= 1e3 ? round($a / 1e3, 1).'k' : (string) round($a));

        return ($v < 0 ? '−' : '').$s;
    }
}
