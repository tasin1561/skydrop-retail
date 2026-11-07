<?php

namespace App\Services\Shopping;

use App\Models\Bundle;
use App\Models\Product;
use App\Services\PricingService;
use App\Support\Features;
use App\Support\ProductJson;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Frequently bought together (co-purchases from order_items, falling back to complementary items of the same store)
 * and admin-curated bundles with an extra discount funded from our margin.
 */
class BundleService
{
    /** Complementary categories when there is not enough purchase data. */
    private const COMPLEMENTS = [
        'electronics' => ['electronics', 'home'], 'fashion' => ['fashion', 'beauty'], 'beauty' => ['beauty', 'fashion'],
        'grocery' => ['grocery', 'home'], 'home' => ['home', 'grocery'],
    ];

    public function __construct(private PricingService $pricing, private ProductMatcher $matcher) {}

    /** @return array{source: 'copurchase'|'category', items: list<array<string, mixed>>} */
    public function boughtTogether(Product $p, int $limit = 3): array
    {
        $limit = max(1, min(6, $limit));
        $counts = DB::table('order_items as a')
            ->join('order_items as b', fn ($j) => $j->on('a.order_id', '=', 'b.order_id')->on('a.product_id', '!=', 'b.product_id'))
            ->join('orders as o', 'o.id', '=', 'a.order_id')
            ->where('a.product_id', $p->id)->where('o.status', '!=', 'cancelled')
            ->groupBy('b.product_id')->selectRaw('b.product_id as id, count(distinct a.order_id) as n')
            ->orderByDesc('n')->limit(20)->pluck('n', 'id');

        $exclude = array_merge([$p->id], array_keys($this->matcher->matchesFor($p->id))); // same product elsewhere is a substitute
        $items = collect();
        if ($counts->isNotEmpty()) {
            $found = Product::query()->whereIn('id', $counts->keys())->where('hidden', false)->where('in_stock', true)
                ->whereNotIn('id', $exclude)->get()->keyBy('id');
            $items = $counts->keys()->map(fn ($id) => $found[$id] ?? null)->filter()->take($limit)->values();
        }
        $source = $items->count() >= 1 ? 'copurchase' : 'category';

        if ($items->count() < $limit) {
            $items = $items->concat($this->complements($p, $limit - $items->count(), array_merge($exclude, $items->pluck('id')->all())));
        }

        return [
            'source' => $source,
            'items' => $items->map(fn (Product $x) => ProductJson::one($x) + ['boughtTogether' => (int) ($counts[$x->id] ?? 0)])->values()->all(),
        ];
    }

    /** @return Collection<int, Product> */
    private function complements(Product $p, int $n, array $exclude): Collection
    {
        $cats = self::COMPLEMENTS[$p->category] ?? [$p->category];
        $price = $this->pricing->productBdt($p);
        $pool = Product::query()->where('market', $p->market)->whereIn('category', $cats)->where('hidden', false)->where('in_stock', true)
            ->whereNotIn('id', $exclude)->orderByDesc('reviews')->limit(60)->get();
        $title = ProductMatcher::normTitle((string) $p->title, (string) $p->brand);

        // Complements, not substitutes: different enough titles, and cheaper add-ons first.
        return $pool->reject(fn (Product $x) => ProductMatcher::similarity($title, ProductMatcher::normTitle((string) $x->title, (string) $x->brand)) > 0.45)
            ->sortBy(fn (Product $x) => [$this->pricing->productBdt($x) > $price ? 1 : 0, -$x->reviews])
            ->take($n)->values();
    }

    /** Bundle JSON with priced items. */
    public function toApi(Bundle $b, bool $withItems = true): array
    {
        $b->loadMissing('items.product');
        $items = $b->items->filter(fn ($i) => $i->product)->values();
        $regular = (int) $items->sum(fn ($i) => $this->pricing->productBdt($i->product) * $i->qty);
        $discount = $this->bundleDiscount($items->map(fn ($i) => ['product' => $i->product, 'qty' => $i->qty, 'unit_bdt' => $this->pricing->productBdt($i->product)])->all(), $b->discount_pct);
        $out = [
            'id' => (string) $b->id, 'slug' => $b->slug, 'title' => $b->title, 'description' => $b->description, 'image' => $b->image,
            'discountPct' => (float) $b->discount_pct, 'regularBdt' => $regular, 'discountBdt' => $discount, 'totalBdt' => $regular - $discount,
            'available' => $items->isNotEmpty() && $items->every(fn ($i) => $i->product->in_stock && ! $i->product->hidden),
            'endsAt' => $b->ends_at?->toIso8601String(), 'count' => $items->count(),
        ];
        if ($withItems) {
            $out['items'] = $items->map(fn ($i) => ['qty' => $i->qty, 'product' => ProductJson::one($i->product)])->values()->all();
        }

        return $out;
    }

    /** Live bundles that contain a product. */
    public function forProduct(string $productId): Collection
    {
        return Bundle::query()->live()->whereHas('items', fn ($q) => $q->where('product_id', $productId))->orderBy('sort')->get();
    }

    /**
     * Discount for curated bundles fully present in the cart (each line unit is used by at most one bundle;
     * the biggest saving wins). Feature switch "bundles" off → no discount.
     *
     * @param  array<int, array<string, mixed>>  $lines  CheckoutService lines (product_id, qty, unit_bdt, category)
     * @return array{discount: int, applied: list<array{id: string, slug: string, title: string, sets: int, discountBdt: int}>}
     */
    public function discountFor(array $lines): array
    {
        $none = ['discount' => 0, 'applied' => []];
        if (! $lines || ! Features::enabled('bundles')) {
            return $none;
        }
        $left = [];
        $units = [];
        foreach ($lines as $l) {
            $left[$l['product_id']] = ($left[$l['product_id']] ?? 0) + (int) $l['qty'];
            $units[$l['product_id']] = $l;
        }
        $bundles = Bundle::query()->live()->with('items')
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', array_keys($left)))->get()
            ->filter(fn (Bundle $b) => $b->items->isNotEmpty() && $b->items->every(fn ($i) => isset($left[$i->product_id])));
        if ($bundles->isEmpty()) {
            return $none;
        }
        $products = Product::query()->whereIn('id', array_keys($left))->get()->keyBy('id');

        $setValue = function (Bundle $b) use ($units, $products) {
            return $this->bundleDiscount($b->items->map(fn ($i) => ['product' => $products[$i->product_id] ?? null, 'qty' => $i->qty,
                'unit_bdt' => (float) $units[$i->product_id]['unit_bdt']])->all(), $b->discount_pct);
        };
        $applied = [];
        $total = 0;
        foreach ($bundles->sortByDesc($setValue) as $b) {
            $sets = (int) min($b->items->map(fn ($i) => intdiv($left[$i->product_id], max(1, $i->qty)))->all());
            if ($sets < 1) {
                continue;
            }
            foreach ($b->items as $i) {
                $left[$i->product_id] -= $sets * max(1, $i->qty);
            }
            $d = $setValue($b) * $sets;
            $total += $d;
            $applied[] = ['id' => (string) $b->id, 'slug' => $b->slug, 'title' => $b->title, 'sets' => $sets, 'discountBdt' => $d];
        }

        return ['discount' => $total, 'applied' => $applied];
    }

    /**
     * Discount for one set: pct of the set price, capped at our margin on those items (never sell below cost).
     *
     * @param  array<int, array{product: ?Product, qty: int, unit_bdt: float}>  $items
     */
    public function bundleDiscount(array $items, float $pct): int
    {
        $price = 0.0;
        $margin = 0.0;
        foreach ($items as $i) {
            $line = (float) $i['unit_bdt'] * max(1, (int) $i['qty']);
            $price += $line;
            $m = $i['product'] ? $this->pricing->rule((string) $i['product']->category)['margin'] : 0.10;
            $margin += $line - $line / (1 + $m);
        }

        return (int) floor(min($price * max(0, $pct) / 100, $margin));
    }
}
