<?php

namespace App\Services\Categories;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDetail;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Write side of the tree: moving and reordering nodes, assigning products (auto/manual) and recounting.
 * Path/depth/sort_key recomputation itself lives in Category model events, so every write path stays consistent.
 */
class CategoryWriter
{
    public function __construct(private CategoryTree $tree, private CategoryMatcher $matcher) {}

    /** Move a node under a new parent (null = top level), appended last. Recomputes the subtree paths. */
    public function move(Category $node, ?int $parentId): Category
    {
        if ($parentId) {
            $parent = Category::query()->tree()->findOrFail($parentId);
            if ($parent->id === $node->id || str_starts_with($parent->path.'/', $node->path.'/')) {
                throw new InvalidArgumentException('A category cannot be moved inside itself.');
            }
        }
        $clash = Category::query()->tree()->where('parent_id', $parentId)->where('slug', $node->slug)->whereKeyNot($node->id)->exists();
        if ($clash) {
            throw new InvalidArgumentException("The new parent already has a child called \"{$node->slug}\".");
        }
        $sort = (int) Category::query()->tree()->where('parent_id', $parentId)->max('sort') + 1;
        DB::transaction(fn () => $node->update(['parent_id' => $parentId, 'sort' => $sort]));

        return $node->refresh();
    }

    /** Swap a node with its previous (-1) or next (+1) sibling. */
    public function shift(Category $node, int $dir): void
    {
        $siblings = Category::query()->tree()->where('parent_id', $node->parent_id)->orderBy('sort')->orderBy('id')->get()->values();
        $i = $siblings->search(fn (Category $c) => $c->id === $node->id);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($j === false || $j < 0 || $j >= $siblings->count()) {
            return;
        }
        $ids = $siblings->pluck('id')->all();
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->reorder($ids);
    }

    /** @param list<int> $ids siblings in their new order */
    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                $c = Category::query()->tree()->find($id);
                if ($c && $c->sort !== $i + 1) {
                    $c->update(['sort' => $i + 1]);
                }
            }
        });
    }

    /**
     * Assign products to a node by hand (admin bulk assign). Manual assignments are never overwritten by the matcher.
     *
     * @param  list<string>  $productIds
     */
    public function assign(array $productIds, Category $node): int
    {
        $group = $node->effectivePricingGroup();
        $n = 0;
        foreach (Product::query()->whereIn('id', $productIds)->get() as $p) {
            $p->forceFill(['category_id' => $node->id, 'category_source' => 'manual', 'category' => $group])->saveQuietly();
            $n++;
        }
        CategoryTree::flush();

        return $n;
    }

    /**
     * Run the matcher over products. Without $all only uncategorised products; with $all every product except manual
     * assignments ($force includes those too). Returns [matched, unmatched].
     *
     * @return array{0: int, 1: int}
     */
    public function categorize(bool $all = false, bool $force = false, ?array $ids = null, ?callable $tick = null): array
    {
        if (! $this->tree->exists()) {
            return [0, 0];
        }
        $q = Product::query()->when($ids, fn ($q) => $q->whereIn('id', $ids));
        if (! $all && ! $ids) {
            $q->whereNull('category_id');
        } elseif (! $force) {
            $q->where(fn ($w) => $w->whereNull('category_source')->orWhere('category_source', '!=', 'manual'));
        }
        $matched = $unmatched = 0;
        $q->orderBy('id')->chunk(500, function ($chunk) use (&$matched, &$unmatched, $tick) {
            $crumbs = ProductDetail::query()->whereIn('product_id', $chunk->pluck('id'))->pluck('category_path', 'product_id');
            foreach ($chunk as $p) {
                $path = $crumbs[$p->id] ?? null;
                $path = is_string($path) ? (array) json_decode($path, true) : (array) $path;
                $m = $this->matcher->match($p->market, (string) $p->title, $path, $p->category);
                if ($m) {
                    $p->forceFill(['category_id' => $m['id'], 'category_source' => 'auto', 'category' => $m['pricing_group']])->saveQuietly();
                    $matched++;
                } else {
                    $unmatched++;
                }
                $tick && $tick();
            }
        });
        CategoryTree::flush();

        return [$matched, $unmatched];
    }

    /** Recompute the cached product_count (visible products in the subtree) for every tree node. */
    public function recount(): int
    {
        CategoryTree::flush();
        $counts = $this->tree->counts();
        $n = 0;
        foreach (Category::query()->tree()->get(['id', 'product_count']) as $c) {
            $v = $counts[$c->id]['all'] ?? 0;
            if ((int) $c->product_count !== $v) {
                Category::query()->whereKey($c->id)->update(['product_count' => $v]); // no events: does not bust the tree cache
                $n++;
            }
        }

        return $n;
    }
}
