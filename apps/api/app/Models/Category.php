<?php

namespace App\Models;

use App\Services\Categories\CategoryTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Two kinds of rows share this table:
 *  - unified tree nodes (market = 'all'): the Skydrop Retail taxonomy, keyed by `path`, mapped to each store via `sources`;
 *  - legacy per-store seeding rows (market = amazon|flipkart|bigbasket|myntra) used by retail:seed-bestsellers.
 *
 * For tree nodes, `path`, `depth` and `sort_key` are derived from the parent on every save, and a changed path or
 * order cascades to the children, so moving a node in the admin recomputes the whole subtree.
 */
class Category extends Model
{
    public const UNIFIED = 'all';

    public const PRICING_GROUPS = ['electronics', 'fashion', 'beauty', 'grocery', 'home'];

    public const STORES = ['amazon', 'flipkart', 'bigbasket', 'myntra'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean', 'featured' => 'boolean', 'sources' => 'array', 'keywords' => 'array',
            'depth' => 'integer', 'sort' => 'integer', 'product_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $c) {
            if ($c->market === self::UNIFIED) {
                $c->derivePath();
            }
        });
        static::saved(function (Category $c) {
            if ($c->market === self::UNIFIED && ($c->wasChanged('path') || $c->wasChanged('sort_key') || $c->wasChanged('active'))) {
                foreach ($c->children()->get() as $child) {
                    $child->save(); // recomputes path, depth and sort_key from this node
                }
            }
            CategoryTree::flush();
        });
        static::deleted(function (Category $c) {
            if ($c->market === self::UNIFIED) {
                // Children move up to the deleted node's parent; products fall back to "unmatched".
                foreach ($c->children()->get() as $child) {
                    $child->update(['parent_id' => $c->parent_id]);
                }
                Product::query()->where('category_id', $c->id)->update(['category_id' => null, 'category_source' => null]);
            }
            CategoryTree::flush();
        });
    }

    /** Recompute path, depth and sort_key from the parent (tree nodes only). */
    public function derivePath(): void
    {
        $parent = $this->parent_id ? self::query()->find($this->parent_id) : null;
        $segment = $this->slug ?: basename((string) $this->path);
        $this->slug = $segment;
        $this->path = $parent ? $parent->path.'/'.$segment : $segment;
        $this->depth = $parent ? (int) $parent->depth + 1 : 1;
        $key = sprintf('%04d', max(0, (int) $this->sort));
        $this->sort_key = $parent ? $parent->sort_key.'.'.$key : $key;
    }

    public function scopeTree(Builder $q): Builder
    {
        return $q->where('market', self::UNIFIED);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /** Pricing group, inherited from the nearest ancestor that sets one. */
    public function effectivePricingGroup(): string
    {
        $node = $this;
        for ($i = 0; $node && $i < 8; $i++) {
            if ($node->pricing_group) {
                return $node->pricing_group;
            }
            $node = $node->parent;
        }

        return 'home';
    }

    /** @return list<int> this node and every descendant */
    public function subtreeIds(): array
    {
        return self::query()->tree()->where(fn ($q) => $q->whereKey($this->id)->orWhere('path', 'like', addcslashes((string) $this->path, '%_\\').'/%'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<string> */
    public function stores(): array
    {
        return array_values(array_intersect(self::STORES, array_keys((array) $this->sources)));
    }
}
