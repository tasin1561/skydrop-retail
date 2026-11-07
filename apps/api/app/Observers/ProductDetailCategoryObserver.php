<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductDetail;
use App\Services\Categories\CategoryMatcher;
use App\Services\Categories\CategoryTree;

/** A detail fetch brings the store breadcrumbs: re-match the product with them (breadcrumbs beat titles and hints). */
class ProductDetailCategoryObserver
{
    public function __construct(private CategoryTree $tree, private CategoryMatcher $matcher) {}

    public function saved(ProductDetail $d): void
    {
        $crumbs = array_values(array_filter((array) $d->category_path, 'is_string'));
        if (! $crumbs || ! $d->wasChanged('category_path') && ! $d->wasRecentlyCreated || ! $this->tree->exists()) {
            return;
        }
        $p = Product::query()->find($d->product_id);
        if (! $p || $p->category_source === 'manual') {
            return;
        }
        $m = $this->matcher->match((string) $p->market, (string) $p->title, $crumbs, $p->category ?: null);
        if ($m && ((int) $p->category_id !== $m['id'] || $p->category !== $m['pricing_group'])) {
            Product::query()->whereKey($p->id)->update(['category_id' => $m['id'], 'category_source' => 'auto', 'category' => $m['pricing_group']]);
            CategoryTree::flushCounts();
        }
    }
}
