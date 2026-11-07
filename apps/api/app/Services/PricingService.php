<?php

namespace App\Services;

use App\Models\FxRate;
use App\Models\PricingRule;
use Illuminate\Support\Facades\Cache;

// Same formula as apps/web/src/lib/pricing.ts; rules come from the pricing_rules table when present.
class PricingService
{
    private const IMPORT = ['electronics' => 15, 'fashion' => 10, 'beauty' => 10, 'grocery' => 5, 'home' => 10];
    private const WEIGHT = ['electronics' => 0.4, 'fashion' => 0.6, 'beauty' => 0.15, 'grocery' => 1, 'home' => 1.2];

    public function fx(): float
    {
        return Cache::remember('fx:INRBDT', 600, fn () => (float) (FxRate::query()->latest('fetched_at')->value('rate') ?? config('retail.pricing.fx_inr_bdt')));
    }

    /** @return array{margin: float, import: float, weight: float} */
    public function rule(string $category): array
    {
        $rules = Cache::remember('pricing:rules', 600, fn () => PricingRule::query()->get()->keyBy('category'));
        $r = $rules[$category] ?? $rules['*'] ?? null;

        return [
            'margin' => $r ? (float) $r->margin_pct / 100 : 0.10,
            'import' => $r ? (float) $r->import_pct / 100 : (self::IMPORT[$category] ?? 10) / 100,
            'weight' => $r ? (float) $r->default_weight_kg : (self::WEIGHT[$category] ?? 0.5),
        ];
    }

    /** INR we actually pay at the store: the effective (coupon/deal) price when it is lower. */
    public function buyInr(object $p): float
    {
        $price = (float) ($p->price_inr ?? 0);
        $eff = (float) ($p->effective_price_inr ?? 0);
        // Offers (deal price minus clippable coupon) only count while the deal is live and coupons are passed through.
        $ends = $p->deal_ends_at ?? null;
        $expired = $ends !== null && \Illuminate\Support\Carbon::parse($ends)->isPast();
        $pass = \App\Services\Catalog\RetailSettings::bool('pass_coupons', (bool) config('retail.offers.pass_coupons', true));

        return $pass && ! $expired && $eff > 0 && $eff < $price ? $eff : $price;
    }

    /** Shopper price for a product row; single source of truth for catalog, checkout and orders. */
    public function productBdt(object $p): int
    {
        return $this->priceBdt($this->buyInr($p), (string) ($p->category ?? ''));
    }

    public function priceBdt(float $inr, string $category): int
    {
        $r = $this->rule($category);
        $base = $inr * $this->fx() * (1 + config('retail.pricing.fx_buffer'));

        return (int) (ceil($base * (1 + $r['import']) * (1 + $r['margin']) / 10) * 10);
    }

    /** @return array{store: int, import: int, service: int, total: int} */
    public function breakdown(float $inr, string $category): array
    {
        $r = $this->rule($category);
        $base = $inr * $this->fx() * (1 + config('retail.pricing.fx_buffer'));
        $import = $base * $r['import'];
        $total = $this->priceBdt($inr, $category);

        return ['store' => (int) round($base), 'import' => (int) round($import), 'service' => (int) max(0, $total - round($base) - round($import)), 'total' => $total];
    }

    public function weightKg(?string $weight, string $category): float
    {
        if ($weight && preg_match('/([\d.]+)\s*(kg|kilo|g|gram)/i', $weight, $m)) {
            $v = (float) $m[1];

            return stripos($m[2], 'k') === 0 ? $v : $v / 1000;
        }

        return $this->rule($category)['weight'];
    }

    /** @param array<int, array{unit_bdt: float, qty: int, weight_kg: float}> $items */
    public function shipping(array $items): int
    {
        $s = config('retail.pricing.shipping');
        $sub = array_sum(array_map(fn ($i) => $i['unit_bdt'] * $i['qty'], $items));
        if ($sub >= $s['free_above_bdt']) {
            return 0;
        }
        $kg = max(0.5, array_sum(array_map(fn ($i) => $i['weight_kg'] * $i['qty'], $items)));

        return (int) (ceil(($s['base_bdt'] + $s['per_kg_bdt'] * $kg) / 10) * 10);
    }
}
