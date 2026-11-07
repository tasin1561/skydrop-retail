<?php

namespace App\Services\Catalog;

use App\Models\Marketplace;
use App\Models\Product;
use App\Services\Categories\CategoryTree;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read side of the catalog over the local DB. Search scoring, filters and sorts mirror apps/web/src/lib/catalog.ts
 * so the API and the bundled demo catalog rank products identically.
 */
class CatalogQuery
{
    public const PER_PAGE = 24;

    private const SCAN_LIMIT = 5000;

    /** Brand filter limits (GET /search?brand=a,b and /products/popular). */
    public const MAX_BRANDS = 10;

    public const BRAND_MAX_LEN = 60;

    /** Brand facets: the top N brands of a result set, each count capped, cached per filter set (anti-enumeration). */
    public const BRAND_FACETS = 20;

    public const BRAND_COUNT_CAP = 500;

    private const BRAND_FACET_TTL = 600;

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
     * Parses "Nike,adidas , nike" into a de-duplicated list (original spelling of the first occurrence kept).
     * Returns null when the value breaks the limits (more than MAX_BRANDS, or a name longer than BRAND_MAX_LEN).
     *
     * @return list<string>|null
     */
    public static function parseBrands(string|array|null $raw): ?array
    {
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $out = [];
        foreach ($parts as $b) {
            $b = trim(preg_replace('/\s+/u', ' ', (string) $b) ?? '');
            if ($b === '') {
                continue;
            }
            if (mb_strlen($b) > self::BRAND_MAX_LEN) {
                return null;
            }
            $out[mb_strtolower($b)] ??= $b;
        }

        return count($out) > self::MAX_BRANDS ? null : array_values($out);
    }

    /** Case-insensitive exact match on products.brand (index-friendly on MySQL, whose default collation ignores case). */
    public static function whereBrands(Builder $q, array $brands): Builder
    {
        if (! $brands) {
            return $q;
        }
        $driver = $q->getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $q->whereIn('brand', $brands);
        }
        $lower = array_map('mb_strtolower', $brands);

        return $q->whereRaw('LOWER(brand) IN ('.implode(',', array_fill(0, count($lower), '?')).')', $lower);
    }

    /**
     * @param  array{q?: ?string, store?: ?string, category?: ?string, brand?: string|list<string>|null, sort?: ?string, min?: ?int, max?: ?int, rating?: ?float, fast?: bool, page?: int, perPage?: int}  $sp
     * @param  array<string, int>  $boost  product id => position in a cached store result page (included even when words do not match)
     * @return array{total: int, page: int, pages: int, items: Collection<int, Product>, facets: array{categories: list<array<string, mixed>>, brands: list<array{name: string, count: int}>}}
     */
    public function search(array $sp, array $boost = []): array
    {
        $words = self::words($sp['q'] ?? null);
        $brands = self::parseBrands($sp['brand'] ?? null) ?? [];
        $list = $this->scan($sp, $words, $boost, $brands);

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
            'facets' => [
                // child-category counts of the whole result (top-level nodes when no tree path was given)
                ...app(CategoryTree::class)->facets(array_map(fn ($x) => $x['p']->category_id, $list), $sp['category'] ?? null),
                // top brands of the result ignoring the brand filter itself, so shoppers can switch brands
                'brands' => $this->brandFacets($sp, $words, $boost, $brands ? null : $list),
            ],
        ];
    }

    /**
     * Matching products (every filter but sort/paging), unsorted.
     *
     * @param  list<string>  $words
     * @param  array<string, int>  $boost
     * @param  list<string>  $brands
     * @return list<array{p: Product, s: int, pos: int}>
     */
    private function scan(array $sp, array $words, array $boost, array $brands): array
    {
        $q = $this->visible($sp['store'] ?? null);
        if (! empty($sp['category'])) {
            // A tree path (fashion/men/clothing) includes its descendants; the five pricing groups keep the legacy filter.
            $nodeIds = app(CategoryTree::class)->filterIds((string) $sp['category']);
            $nodeIds === null ? $q->where('category', $sp['category']) : $q->whereIn('category_id', $nodeIds);
        }
        self::whereBrands($q, $brands);
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

        $wanted = $brands ? array_flip(array_map('mb_strtolower', $brands)) : null;
        $list = [];
        foreach ($q->orderBy('created_at')->orderBy('id')->limit(self::SCAN_LIMIT)->get() as $p) {
            if ($wanted !== null && ! isset($wanted[mb_strtolower(trim((string) $p->brand))])) {
                continue; // brand filtered in SQL too; this keeps trimmed/odd values exact on every driver
            }
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

        return $list;
    }

    /**
     * Top brands (name + capped count) of the result set without the brand filter. Cached per filter set, so paging
     * or switching brands never re-scans, and only the top BRAND_FACETS names are ever exposed for a query.
     *
     * @param  list<array{p: Product, s: int, pos: int}>|null  $unbranded  the scan without a brand filter, when already done
     * @return list<array{name: string, count: int}>
     */
    private function brandFacets(array $sp, array $words, array $boost, ?array $unbranded): array
    {
        $key = 'catalog:brand-facets:'.md5(json_encode([
            $words, $sp['store'] ?? null, $sp['category'] ?? null, (int) ($sp['min'] ?? 0), (int) ($sp['max'] ?? 0),
            (float) ($sp['rating'] ?? 0), (bool) ($sp['fast'] ?? false), Marketplace::enabledIds(), array_keys($boost),
        ]));

        return Cache::remember($key, self::BRAND_FACET_TTL, function () use ($sp, $words, $boost, $unbranded) {
            $counts = [];
            $names = [];
            foreach ($unbranded ?? $this->scan($sp, $words, $boost, []) as $x) {
                $name = trim((string) $x['p']->brand);
                if ($name === '' || mb_strlen($name) > self::BRAND_MAX_LEN) {
                    continue;
                }
                $k = mb_strtolower($name);
                $counts[$k] = ($counts[$k] ?? 0) + 1;
                $names[$k] ??= $name;
            }
            uksort($counts, fn ($a, $b) => [$counts[$b], $a] <=> [$counts[$a], $b]);

            return array_map(fn ($k) => ['name' => $names[$k], 'count' => min($counts[$k], self::BRAND_COUNT_CAP)],
                array_slice(array_keys($counts), 0, self::BRAND_FACETS));
        });
    }

    /** @return list<array{id: string, title: string, market: string}> */
    public function suggest(string $q, ?string $store, int $limit = 6): array
    {
        return $this->search(['q' => $q, 'store' => $store, 'perPage' => $limit])['items']
            ->map(fn (Product $p) => ['id' => $p->id, 'title' => $p->title, 'market' => $p->market])->all();
    }

    /** @return Collection<int, Product> */
    public function popular(?string $store, int $limit = 12, ?string $category = null, array $brands = []): Collection
    {
        $nodeIds = $category ? app(CategoryTree::class)->filterIds($category) : null;

        return self::whereBrands($this->visible($store), $brands)
            ->when($category, fn ($q) => $nodeIds === null ? $q->where('category', $category) : $q->whereIn('category_id', $nodeIds))
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
