<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Services\Categories\CategoryMatcher;
use App\Services\Categories\CategoryTree;
use App\Services\Catalog\CategoryGuesser;

/**
 * Keeps products.category_id (tree node) and products.category (pricing group) in step on every upsert, without
 * touching the catalog writer:
 *  - `category = "cat:{id}"` is a hint written by retail:seed-category (the search was run for that node): it becomes
 *    category_id (unless the product already has one) and is replaced by the node's pricing group;
 *  - otherwise uncategorised products (and auto-matched ones whose title changed) go through the CategoryMatcher.
 * Manual (admin) assignments are never changed.
 */
class ProductCategoryObserver
{
    public const HINT = 'cat:';

    public function __construct(private CategoryTree $tree, private CategoryMatcher $matcher) {}

    public function saving(Product $p): void
    {
        $category = (string) $p->category;
        // A search run with category=<tree path> hands the path to the writer: treat it like a hint too.
        $isPath = $category !== '' && ! in_array($category, Category::PRICING_GROUPS, true) && str_contains($category, '/') || ($category !== '' && ! in_array($category, Category::PRICING_GROUPS, true) && $this->tree->byPath($category));
        if (str_starts_with($category, self::HINT) || $isPath) {
            $node = $isPath ? $this->tree->byPath($category) : $this->tree->find((int) substr($category, strlen(self::HINT)));
            if ($node && ! $p->category_id) {
                $p->category_id = $node['id'];
                $p->category_source = 'hint';
            }
            $current = $p->category_id ? $this->tree->find((int) $p->category_id) : null;
            $p->category = $current['pricing_group'] ?? $node['pricing_group']
                ?? app(CategoryGuesser::class)->guess((string) $p->market, (string) $p->title);
            if ($node) {
                return;
            }
        }
        if ($p->category_source === 'manual' || ! $this->tree->exists()) {
            return;
        }
        if ($p->category_id && ! ($p->category_source === 'auto' && $p->isDirty('title'))) {
            return;
        }
        $raw = $p->exists ? ProductDetail::query()->whereKey($p->id)->value('category_path') : null;
        $crumbs = is_string($raw) ? (array) json_decode($raw, true) : [];
        if ($m = $this->matcher->match((string) $p->market, (string) $p->title, $crumbs, $p->category ?: null)) {
            $p->category_id = $m['id'];
            $p->category_source = 'auto';
            $p->category = $m['pricing_group'];
        }
    }

    public function saved(Product $p): void
    {
        if ($p->wasChanged('category_id') || $p->wasChanged('hidden')) {
            CategoryTree::flushCounts();
        }
    }
}
