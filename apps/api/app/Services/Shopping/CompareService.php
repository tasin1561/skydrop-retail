<?php

namespace App\Services\Shopping;

use App\Models\Product;
use App\Services\PricingService;
use App\Support\ProductJson;

/** "Compare stores": the same product on other listings, priced in BDT with a delivery estimate. */
class CompareService
{
    public function __construct(private ProductMatcher $matcher, private PricingService $pricing) {}

    /** @return array{productId: string, items: list<array<string, mixed>>, bestId: ?string, savingBdt: int} */
    public function compare(Product $p): array
    {
        $matches = $this->matcher->matchesFor($p->id);
        if (! $matches) {
            return ['productId' => $p->id, 'items' => [], 'bestId' => null, 'savingBdt' => 0];
        }
        $others = Product::query()->whereIn('id', array_keys($matches))->where('hidden', false)->get();
        $rows = collect([$p])->concat($others)->map(fn (Product $x) => [
            'productId' => $x->id,
            'market' => $x->market,
            'priceBdt' => $this->pricing->productBdt($x),
            'inStock' => (bool) $x->in_stock,
            'delivery' => ProductMatcher::delivery($x),
            'current' => $x->id === $p->id,
            'match' => $x->id === $p->id ? null : ['method' => $matches[$x->id]['method'], 'score' => $matches[$x->id]['score']],
            'product' => ProductJson::one($x),
        ])->values();

        // Best price among in-stock listings; ties go to the faster delivery.
        $best = $rows->filter(fn ($r) => $r['inStock'])->sortBy([['priceBdt', 'asc'], [fn ($a, $b) => $a['delivery']['max'] <=> $b['delivery']['max']]])->first();
        $items = $rows->map(fn ($r) => $r + ['best' => $best && $r['productId'] === $best['productId'] && $rows->count() > 1])
            ->sortBy([['priceBdt', 'asc']])->values()->all();
        $currentPrice = $rows->firstWhere('current', true)['priceBdt'];

        return [
            'productId' => $p->id,
            'items' => count($items) > 1 ? $items : [],
            'bestId' => count($items) > 1 ? $best['productId'] ?? null : null,
            'savingBdt' => $best ? max(0, $currentPrice - $best['priceBdt']) : 0,
        ];
    }

    /**
     * Cheaper identical in-stock listings for cart lines (checkout suggestion).
     *
     * @param  list<array{productId: string, variant?: ?string, qty?: int}>  $items
     * @return list<array<string, mixed>>
     */
    public function cheaperFor(array $items): array
    {
        $out = [];
        $ids = array_values(array_unique(array_map(fn ($i) => (string) $i['productId'], $items)));
        $products = Product::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($items as $i) {
            $p = $products[(string) $i['productId']] ?? null;
            if (! $p || isset($out[$p->id])) {
                continue;
            }
            $mine = $this->pricing->productBdt($p);
            $matches = $this->matcher->matchesFor($p->id);
            if (! $matches) {
                continue;
            }
            $best = null;
            foreach (Product::query()->whereIn('id', array_keys($matches))->where('hidden', false)->where('in_stock', true)->get() as $o) {
                $price = $this->pricing->productBdt($o);
                if ($price < $mine && (! $best || $price < $best['priceBdt']) && ! in_array($o->id, $ids, true)) {
                    $best = ['priceBdt' => $price, 'product' => $o];
                }
            }
            // Only worth suggesting when it saves at least ৳20.
            if ($best && $mine - $best['priceBdt'] >= 20) {
                $qty = max(1, (int) ($i['qty'] ?? 1));
                $out[$p->id] = [
                    'productId' => $p->id, 'variant' => $i['variant'] ?? null, 'qty' => $qty, 'title' => $p->title, 'market' => $p->market,
                    'unitBdt' => $mine, 'alternativeId' => $best['product']->id, 'alternativeMarket' => $best['product']->market,
                    'alternativeBdt' => $best['priceBdt'], 'savingBdt' => ($mine - $best['priceBdt']) * $qty,
                    'delivery' => ProductMatcher::delivery($best['product']), 'alternative' => ProductJson::one($best['product']),
                ];
            }
        }

        return array_values($out);
    }
}
