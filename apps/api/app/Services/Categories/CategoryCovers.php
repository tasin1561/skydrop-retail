<?php

namespace App\Services\Categories;

use App\Models\Category;
use App\Models\Product;

/**
 * Picks up to 4 cover photos for every node of the category tree from the products we already have, so every
 * category and sub-category shows real photos (like Myntra, Flipkart and Amazon do) instead of a line icon.
 *
 * Best first: well rated products with many reviews; for fashion and beauty, Myntra and Flipkart photos come first
 * because they are shot on models; never two photos of the same brand while another brand is available. An admin
 * `image` on a node always stays in front (see CategoryTree::present()).
 */
class CategoryCovers
{
    public const PER_NODE = 4;

    private const CANDIDATES = 40;

    public function __construct(private CategoryTree $tree) {}

    /** @return int nodes whose covers changed */
    public function refresh(): int
    {
        $nodes = $this->tree->nodes();
        $changed = 0;
        foreach ($nodes as $id => $n) {
            $ids = $this->subtreeIds($nodes, $id);
            $covers = $this->pick($ids, (string) $n['pricing_group']);
            $old = Category::query()->whereKey($id)->value('covers');
            $old = is_string($old) ? json_decode($old, true) : $old;
            if (($old ?: []) !== $covers) {
                // Query builder: no model events, so the tree cache is flushed once at the end, not 500 times.
                Category::query()->whereKey($id)->update(['covers' => $covers ? json_encode($covers) : null]);
                $changed++;
            }
        }
        CategoryTree::flush();

        return $changed;
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<string>
     */
    public function pick(array $categoryIds, string $group): array
    {
        $rows = Product::query()
            ->whereIn('category_id', $categoryIds)->where('hidden', false)->where('in_stock', true)
            ->whereNotNull('image')->where('image', '!=', '')
            ->orderByDesc('reviews')->orderByDesc('rating')->limit(self::CANDIDATES)
            ->get(['id', 'market', 'brand', 'image', 'rating', 'reviews']);

        $model = in_array($group, ['fashion', 'beauty'], true);
        $scored = $rows->map(function (Product $p) use ($model) {
            $score = (float) $p->rating * log10((int) $p->reviews + 10);
            if ($model && in_array($p->market, ['myntra', 'flipkart'], true)) {
                $score *= 1.6;
            }
            if ((float) $p->rating > 0 && (float) $p->rating < 3.5) {
                $score *= 0.3;
            }

            return ['image' => (string) $p->image, 'brand' => mb_strtolower(trim((string) $p->brand)), 'score' => $score];
        })->sortByDesc('score')->values();

        $out = [];
        $brands = [];
        foreach ([true, false] as $distinct) { // first pass: one photo per brand; second: fill up
            foreach ($scored as $r) {
                if (count($out) >= self::PER_NODE) {
                    break 2;
                }
                if (in_array($r['image'], $out, true) || ($distinct && $r['brand'] !== '' && isset($brands[$r['brand']]))) {
                    continue;
                }
                $out[] = $r['image'];
                $brands[$r['brand']] = true;
            }
        }

        return $out;
    }

    /** @return list<int> */
    private function subtreeIds(array $nodes, int $id): array
    {
        $out = [$id];
        foreach ($nodes[$id]['children'] ?? [] as $c) {
            array_push($out, ...$this->subtreeIds($nodes, $c));
        }

        return $out;
    }
}
