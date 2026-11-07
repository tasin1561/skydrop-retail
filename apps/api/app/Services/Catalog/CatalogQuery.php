<?php

namespace App\Services\Catalog;

use App\Models\Marketplace;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read side of the catalog over the local DB. Search scoring, filters and sorts mirror apps/web/src/lib/catalog.ts
 * so the API and the bundled demo catalog rank products identically.
 */
class CatalogQuery
{
    public const PER_PAGE = 24;

    private const SCAN_LIMIT = 5000;

    public function __construct(private PricingService $pricing) {}

    /** @return list<string> enabled marketplaces within the requested store scope */
    public function markets(?string $store): array
    {
        $enabled = Marketplace::enabledIds();

        return $store && $store !== 'all' ? array_values(array_intersect($enabled, [$store])) : $enabled;
    }

    public function visible(?string $store): Builder
    {
        return Product::query()->where('hidden', false)->whereIn('market', $this->markets($store));
    }

    /** @return list<string> */
    public static function words(?string $q): array
    {
        return array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim((string) $q))) ?: [], fn ($w) => mb_strlen($w) > 1));
    }

    /** Same as catalog.ts score(): every word must appear; +3 when the title starts with it, else +1. */
    public static function score(Product $p, array $words): int
    {
        if (! $words) {
            return 1;
        }
        $title = mb_strtolower((string) $p->title);
        $hay = $title.' '.mb_strtolower((string) $p->brand).' '.mb_strtolower((string) $p->category);
        $s = 0;
        foreach ($words as $w) {
            if (! str_contains($hay, $w)) {
                return 0;
            }
            $s += str_starts_with($title, $w) ? 3 : 1;
        }

        return $s;
    }

    /**
     * @param  array{q?: ?string, store?: ?string, category?: ?string, sort?: ?string, min?: ?int, max?: ?int, rating?: ?float, fast?: bool, page?: int, perPage?: int}  $sp
     * @param  array<string, int>  $boost  product id => position in a cached store result page (included even when words do not match)
     * @return array{total: int, page: int, pages: int, items: Collection<int, Product>}
     */
    public function search(array $sp, array $boost = []): array
    {
        $words = self::words($sp['q'] ?? null);
        $q = $this->visible($sp['store'] ?? null);
        if (! empty($sp['category'])) {
            $q->where('category', $sp['category']);
        }
        if ($words) {
            $q->where(function (Builder $w) use ($words, $boost) {
                $w->where(function (Builder $all) use ($words) {
                    foreach ($words as $word) {
                        $like = '%'.addcslashes($word, '%_\\').'%';
                        $all->where(fn (Builder $o) => $o->where('title', 'like', $like)->orWhere('brand', 'like', $like)->orWhere('category', 'like', $like));
                    }
                });
                if ($boost) {
                    $w->orWhereIn('id', array_keys($boost));
                }
            });
        }
        if (! empty($sp['rating'])) {
            $q->where('rating', '>=', (float) $sp['rating']);
        }
        if (! empty($sp['fast'])) {
            $q->where('prime', true);
        }

        $list = [];
        foreach ($q->orderBy('created_at')->orderBy('id')->limit(self::SCAN_LIMIT)->get() as $p) {
            $s = self::score($p, $words);
            if (isset($boost[$p->id])) {
                $s = max($s, 1) + 1; // the store itself ranked it for this query
            }
            if ($s <= 0) {
                continue;
            }
            $bdt = $this->pricing->productBdt($p);
            if ((! empty($sp['min']) && $bdt < (int) $sp['min']) || (! empty($sp['max']) && $bdt > (int) $sp['max'])) {
                continue;
            }
            $list[] = ['p' => $p, 's' => $s, 'pos' => $boost[$p->id] ?? PHP_INT_MAX];
        }

        $disc = fn (Product $p) => $p->mrp_inr > $p->price_inr ? 1 - $p->price_inr / $p->mrp_inr : 0;
        usort($list, match ($sp['sort'] ?? 'relevance') {
            'price_asc' => fn ($a, $b) => $a['p']->price_inr <=> $b['p']->price_inr,
            'price_desc' => fn ($a, $b) => $b['p']->price_inr <=> $a['p']->price_inr,
            'rating' => fn ($a, $b) => [$b['p']->rating, $b['p']->reviews] <=> [$a['p']->rating, $a['p']->reviews],
            'discount' => fn ($a, $b) => $disc($b['p']) <=> $disc($a['p']),
            default => fn ($a, $b) => [$b['s'], $a['pos'], $b['p']->reviews] <=> [$a['s'], $b['pos'], $a['p']->reviews],
        });

        $perPage = max(1, (int) ($sp['perPage'] ?? self::PER_PAGE));
        $page = max(1, (int) ($sp['page'] ?? 1));
        $total = count($list);

        return [
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'items' => collect(array_slice($list, ($page - 1) * $perPage, $perPage))->pluck('p')->values(),
        ];
    }

    /** @return list<array{id: string, title: string, market: string}> */
    public function suggest(string $q, ?string $store, int $limit = 6): array
    {
        return $this->search(['q' => $q, 'store' => $store, 'perPage' => $limit])['items']
            ->map(fn (Product $p) => ['id' => $p->id, 'title' => $p->title, 'market' => $p->market])->all();
    }

    /** @return Collection<int, Product> */
    public function popular(?string $store, int $limit = 12, ?string $category = null): Collection
    {
        return $this->visible($store)->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('reviews')->limit($limit)->get();
    }

    /** Biggest MRP-to-price ratio first, like catalog.ts deals(); offers are loaded for the cards. */
    public function deals(?string $store, int $limit = 12): Collection
    {
        return $this->visible($store)->whereColumn('mrp_inr', '>', 'price_inr')->where('price_inr', '>', 0)
            ->with('details')->orderByRaw('mrp_inr / price_inr DESC')->limit($limit)->get();
    }

    /** @return Collection<int, Product> */
    public function related(Product $p, int $limit = 8): Collection
    {
        return Product::query()->where('hidden', false)->where('market', $p->market)->where('category', $p->category)
            ->where('id', '!=', $p->id)->when($p->parent_id, fn ($q) => $q->where(fn ($w) => $w->whereNull('parent_id')->orWhere('parent_id', '!=', $p->parent_id)))
            ->orderByDesc('reviews')->limit($limit)->get();
    }

    /**
     * Live deals: active coupons / deal prices, biggest saving first. $type filters on the offer type.
     *
     * @return Collection<int, Product>
     */
    public function offers(?string $store, ?string $type, int $limit = 24): Collection
    {
        $rows = $this->visible($store)
            ->where(fn ($q) => $q->whereNotNull('deal_badge')->orWhereNotNull('coupon_inr')->orWhereColumn('effective_price_inr', '<', 'price_inr'))
            ->where(fn ($q) => $q->whereNull('deal_ends_at')->orWhere('deal_ends_at', '>', now()))
            ->with('details')->orderByDesc('saving_inr')->orderByDesc('reviews')
            ->limit($type ? $limit * 10 : $limit)->get();
        if ($type) {
            $rows = $rows->filter(fn (Product $p) => match ($type) {
                'coupon' => $p->coupon_inr > 0 || $this->hasOffer($p, 'coupon'),
                'deal' => $p->deal_badge !== null || $this->hasOffer($p, 'deal'),
                default => $this->hasOffer($p, $type),
            })->take($limit)->values();
        }

        return $rows;
    }

    private function hasOffer(Product $p, string $type): bool
    {
        return collect($p->details?->offers ?? [])->contains(fn ($o) => ($o['type'] ?? null) === $type);
    }
}
