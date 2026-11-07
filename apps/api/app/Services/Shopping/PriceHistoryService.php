<?php

namespace App\Services\Shopping;

use App\Models\PriceHistory;
use App\Models\Product;
use App\Services\PricingService;
use App\Support\Features;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Price history in BDT. History is stored in INR (price_history, written by the catalog when a price changes);
 * every point is converted with TODAY's FX and pricing rules so the chart compares like with like.
 */
class PriceHistoryService
{
    public const PERIODS = [30, 90, 365];

    public function __construct(private PricingService $pricing) {}

    /**
     * Daily series (last price of each day, carried forward) for the last $days days, ending with today's price.
     *
     * @return array{productId: string, days: int, currency: string, points: list<array{date: string, bdt: int, inr: float, inStock: bool}>,
     *     lowestBdt: int, highestBdt: int, averageBdt: int, currentBdt: int, position: float, positionLabel: string, badges: list<array<string, mixed>>}
     */
    public function series(Product $p, int $days): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : 30;
        $category = (string) $p->category;
        $from = now()->startOfDay()->subDays($days - 1);

        $rows = PriceHistory::query()->where('product_id', $p->id)->where('recorded_at', '>=', $from)
            ->orderBy('recorded_at')->orderBy('id')->get(['price_inr', 'in_stock', 'recorded_at']);
        // The price that was in force when the window opened.
        $before = PriceHistory::query()->where('product_id', $p->id)->where('recorded_at', '<', $from)
            ->orderByDesc('recorded_at')->orderByDesc('id')->first(['price_inr', 'in_stock', 'recorded_at']);

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[Carbon::parse($r->recorded_at)->toDateString()] = [(float) $r->price_inr, (bool) $r->in_stock];
        }

        $points = [];
        $carry = $before ? [(float) $before->price_inr, (bool) $before->in_stock] : null;
        $today = now()->toDateString();
        for ($d = $from->copy(); $d->toDateString() <= $today; $d->addDay()) {
            $key = $d->toDateString();
            if (isset($byDay[$key])) {
                $carry = $byDay[$key];
            }
            if ($key === $today) {
                $carry = [$this->pricing->buyInr($p), (bool) $p->in_stock];
            }
            if ($carry === null) {
                continue; // no data yet that far back
            }
            $points[] = ['date' => $key, 'inr' => round($carry[0], 2), 'bdt' => $this->pricing->priceBdt($carry[0], $category), 'inStock' => $carry[1]];
        }

        $bdts = array_column($points, 'bdt');
        $low = min($bdts);
        $high = max($bdts);
        $current = end($points)['bdt'];
        $position = $high > $low ? round(($current - $low) / ($high - $low), 3) : 0.0;

        return [
            'productId' => $p->id,
            'days' => $days,
            'currency' => 'BDT',
            'points' => $points,
            'lowestBdt' => $low,
            'highestBdt' => $high,
            'averageBdt' => (int) round(array_sum($bdts) / count($bdts)),
            'currentBdt' => $current,
            'position' => $position,
            'positionLabel' => $this->positionLabel($position, $high > $low),
            'changes' => count(array_unique($bdts)) - 1,
            'badges' => $this->badges($points),
        ];
    }

    /**
     * Compact insight for product cards: lowest in 30 days, or a price drop this week. Cached for 30 minutes.
     *
     * @return array{type: 'lowest'|'drop', label: string, days?: int, dropBdt?: int}|null
     */
    public function insight(Product $p): ?array
    {
        if (! Features::enabled('price_history')) {
            return null;
        }
        $key = 'pi:'.$p->id.':'.md5((string) $p->price_inr.'|'.$p->effective_price_inr.'|'.$p->category);

        return Cache::remember($key, 1800, function () use ($p) {
            if (! PriceHistory::query()->where('product_id', $p->id)->where('recorded_at', '>=', now()->subDays(30))->exists()) {
                return false; // cached "no insight" (null is never cached)
            }

            return $this->badges($this->series($p, 30)['points'])[0] ?? false;
        }) ?: null;
    }

    /**
     * @param  list<array{date: string, bdt: int, inr: float, inStock: bool}>  $points  daily points ending today
     * @return list<array<string, mixed>>
     */
    public function badges(array $points): array
    {
        if (count($points) < 2) {
            return [];
        }
        $current = end($points)['bdt'];
        $out = [];

        // Price dropped this week: today's price vs. the price 7 days ago (or the oldest point within the week).
        $weekAgo = now()->subDays(7)->toDateString();
        $ref = null;
        foreach ($points as $pt) {
            if ($pt['date'] <= $weekAgo) {
                $ref = $pt['bdt'];
            }
        }
        $ref ??= $points[max(0, count($points) - 8)]['bdt'];
        $drop = $ref - $current;
        if ($drop >= max(20, $ref * 0.02)) {
            $out[] = ['type' => 'drop', 'label' => 'Price dropped ৳'.number_format($drop).' this week', 'dropBdt' => $drop];
        }

        // Lowest price in the last 30 days (only meaningful when the price actually moved).
        $last30 = array_slice($points, -30);
        $vals = array_column($last30, 'bdt');
        if (count($last30) >= 7 && max($vals) > $current && $current <= min($vals)) {
            array_unshift($out, ['type' => 'lowest', 'label' => 'Lowest price in 30 days', 'days' => 30]);
        }

        return $out;
    }

    private function positionLabel(float $position, bool $moved): string
    {
        if (! $moved) {
            return 'steady';
        }

        return match (true) {
            $position <= 0.05 => 'lowest',
            $position <= 0.35 => 'low',
            $position < 0.75 => 'typical',
            default => 'high',
        };
    }
}
