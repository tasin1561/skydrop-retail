<?php

namespace App\Services\Categories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the unified category tree. The whole tree (~530 nodes) is cached as plain arrays (the cache store does
 * not unserialize models) and busted on every Category save/delete. Per-store product counts are cached separately
 * for 10 minutes. Store availability = the store keys present in a node's `sources`.
 */
class CategoryTree
{
    private const CACHE = 'categories:tree:v2';

    private const COUNTS = 'categories:counts:v1';

    /** @var array<int, array<string, mixed>>|null memo for this (singleton) instance */
    private ?array $memo = null;

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
        Cache::forget(self::COUNTS);
        if (app()->resolved(self::class)) {
            app(self::class)->memo = null;
        }
    }

    /** Product counts only (a product was (re)assigned or hidden). */
    public static function flushCounts(): void
    {
        Cache::forget(self::COUNTS);
    }

    /**
     * Active tree nodes in tree order, keyed by id. Each: id, parent_id, path, slug, name, name_bn, icon, image, covers, depth,
     * sort, pricing_group (resolved), sources, keywords, featured, stores, children (ids).
     *
     * @return array<int, array<string, mixed>>
     */
    public function nodes(): array
    {
        return $this->memo ??= Cache::remember(self::CACHE, 3600, function () {
            $rows = Category::query()->tree()->where('active', true)->orderBy('sort_key')->orderBy('id')->get();
            $nodes = [];
            foreach ($rows as $c) {
                $nodes[(int) $c->id] = [
                    'id' => (int) $c->id, 'parent_id' => $c->parent_id ? (int) $c->parent_id : null, 'path' => (string) $c->path,
                    'slug' => (string) $c->slug, 'name' => (string) $c->name, 'name_bn' => $c->name_bn, 'icon' => $c->icon ?: 'package',
                    'image' => $c->image, 'covers' => array_values(array_filter((array) $c->covers, 'is_string')), 'depth' => (int) $c->depth, 'sort' => (int) $c->sort, 'pricing_group' => $c->pricing_group,
                    'sources' => (array) $c->sources, 'keywords' => array_values((array) $c->keywords), 'featured' => (bool) $c->featured,
                    'stores' => $c->stores(), 'children' => [],
                ];
            }
            // Drop nodes whose parent is inactive/missing (an inactive parent hides its subtree).
            foreach ($nodes as $id => $n) {
                if ($n['parent_id'] && ! isset($nodes[$n['parent_id']])) {
                    unset($nodes[$id]);
                }
            }
            foreach ($nodes as $id => $n) {
                if ($n['parent_id'] && isset($nodes[$n['parent_id']])) {
                    $nodes[$n['parent_id']]['children'][] = $id;
                }
            }
            foreach ($nodes as $id => $n) { // tree order means parents resolve first
                $nodes[$id]['pricing_group'] = $n['pricing_group'] ?: ($n['parent_id'] ? ($nodes[$n['parent_id']]['pricing_group'] ?? 'home') : 'home');
            }

            return $nodes;
        });
    }

    public function exists(): bool
    {
        return $this->nodes() !== [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->nodes()[$id] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function byPath(string $path): ?array
    {
        $path = trim(mb_strtolower($path), '/');
        foreach ($this->nodes() as $n) {
            if ($n['path'] === $path) {
                return $n;
            }
        }

        return null;
    }

    /** @return list<int> */
    public function roots(): array
    {
        return array_keys(array_filter($this->nodes(), fn ($n) => $n['parent_id'] === null));
    }

    /** @return list<int> the node and all its descendants */
    public function subtree(int $id): array
    {
        $nodes = $this->nodes();
        $out = [];
        $stack = [$id];
        while ($stack) {
            $cur = array_pop($stack);
            if (! isset($nodes[$cur])) {
                continue;
            }
            $out[] = $cur;
            array_push($stack, ...$nodes[$cur]['children']);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> root first */
    public function ancestry(int $id): array
    {
        $nodes = $this->nodes();
        $chain = [];
        for ($cur = $nodes[$id] ?? null; $cur; $cur = $cur['parent_id'] ? ($nodes[$cur['parent_id']] ?? null) : null) {
            array_unshift($chain, $cur);
        }

        return $chain;
    }

    /**
     * Product filter for search `category=`: the node's subtree ids, or null when the value is one of the five pricing
     * groups (legacy filter on products.category, kept for existing links) or not a known path.
     *
     * @return list<int>|null
     */
    public function filterIds(string $category): ?array
    {
        if (in_array($category, Category::PRICING_GROUPS, true)) {
            return null;
        }
        $node = $this->byPath($category);

        return $node ? $this->subtree($node['id']) : null;
    }

    public static function storeOk(array $node, ?string $store): bool
    {
        return ! $store || $store === 'all' || in_array($store, $node['stores'], true);
    }

    /**
     * Visible product counts per node (subtree, rolled up), per store and 'all'.
     *
     * @return array<int, array<string, int>>
     */
    public function counts(): array
    {
        return Cache::remember(self::COUNTS, 600, function () {
            $nodes = $this->nodes();
            $out = [];
            $rows = Product::query()->where('hidden', false)->whereNotNull('category_id')
                ->select('category_id', 'market', DB::raw('count(*) as n'))->groupBy('category_id', 'market')->get();
            foreach ($rows as $r) {
                for ($cur = $nodes[(int) $r->category_id] ?? null; $cur; $cur = $cur['parent_id'] ? ($nodes[$cur['parent_id']] ?? null) : null) {
                    $out[$cur['id']]['all'] = ($out[$cur['id']]['all'] ?? 0) + (int) $r->n;
                    $out[$cur['id']][$r->market] = ($out[$cur['id']][$r->market] ?? 0) + (int) $r->n;
                }
            }

            return $out;
        });
    }

    public function count(int $id, ?string $store = null): int
    {
        return $this->counts()[$id][$store && $store !== 'all' ? $store : 'all'] ?? 0;
    }

    /**
     * API shape of one node (no children). `image` is the admin's photo, else the best product photo of the node
     * (CategoryCovers); `images` holds up to 4 (admin photo first) for collage tiles. Both are empty only for a node
     * with no products yet: the apps then show the node's icon.
     */
    public function present(array $n, ?string $store = null): array
    {
        $images = array_slice(array_values(array_unique(array_filter([$n['image'] ?? null, ...($n['covers'] ?? [])]))), 0, CategoryCovers::PER_NODE);

        return [
            'id' => $n['id'], 'slug' => $n['slug'], 'path' => $n['path'], 'name' => $n['name'], 'nameBn' => $n['name_bn'],
            'icon' => $n['icon'], 'image' => $images[0] ?? null, 'images' => $images, 'depth' => $n['depth'], 'featured' => $n['featured'],
            'pricingGroup' => $n['pricing_group'], 'stores' => $n['stores'], 'count' => $this->count($n['id'], $store),
        ];
    }

    /**
     * Nested tree for GET /categories, filtered to nodes the store sells, down to $depth levels.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(?string $store = null, ?int $depth = null, ?int $from = null): array
    {
        $nodes = $this->nodes();
        $build = function (int $id) use (&$build, $nodes, $store, $depth): ?array {
            $n = $nodes[$id];
            if (! self::storeOk($n, $store)) {
                return null;
            }
            $out = $this->present($n, $store);
            $out['children'] = [];
            if (! $depth || $n['depth'] < $depth) {
                foreach ($n['children'] as $c) {
                    if ($child = $build($c)) {
                        $out['children'][] = $child;
                    }
                }
            }

            return $out;
        };
        $ids = $from ? $nodes[$from]['children'] ?? [] : $this->roots();

        return array_values(array_filter(array_map($build, $ids)));
    }

    /**
     * Child-category facets for a search result: counts of the matched products under each child of the requested
     * node (or each top-level node when no category / a pricing group was given).
     *
     * @param  iterable<int|null>  $categoryIds  category_id of every matched product
     * @return array{categories: list<array<string, mixed>>}
     */
    public function facets(iterable $categoryIds, ?string $category = null): array
    {
        if (! $this->exists()) {
            return ['categories' => []];
        }
        $nodes = $this->nodes();
        $node = $category ? $this->byPath($category) : null;
        $targets = $node ? $node['children'] : $this->roots();
        if (! $targets) {
            return ['categories' => []];
        }
        $targetSet = array_flip($targets);
        $counts = [];
        foreach ($categoryIds as $cid) {
            for ($cur = $cid ? ($nodes[(int) $cid] ?? null) : null; $cur; $cur = $cur['parent_id'] ? ($nodes[$cur['parent_id']] ?? null) : null) {
                if (isset($targetSet[$cur['id']])) {
                    $counts[$cur['id']] = ($counts[$cur['id']] ?? 0) + 1;
                    break;
                }
            }
        }
        $out = [];
        foreach ($targets as $id) {
            if (! empty($counts[$id])) {
                $n = $nodes[$id];
                $out[] = ['path' => $n['path'], 'name' => $n['name'], 'nameBn' => $n['name_bn'], 'icon' => $n['icon'], 'count' => $counts[$id]];
            }
        }

        return ['categories' => $out];
    }
}
