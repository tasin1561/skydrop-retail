<?php

namespace App\Services\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Loads database/data/category_tree.json into `categories` (market = 'all'), idempotent by path: existing nodes are
 * updated, new ones created. Paths/depth/sort_key are computed here as well (the seeder may run with model events
 * muted). Admin-only fields (image, product_count) and Amazon node ids already discovered by the importer are kept.
 */
class CategoryTreeImporter
{
    public static function file(): string
    {
        return database_path('data/category_tree.json');
    }

    /** @return array{created: int, updated: int, total: int} */
    public function import(?string $file = null): array
    {
        $doc = json_decode((string) file_get_contents($file ?? self::file()), true, 512, JSON_THROW_ON_ERROR);
        $stats = ['created' => 0, 'updated' => 0, 'total' => 0];
        DB::transaction(function () use ($doc, &$stats) {
            foreach ($doc['tree'] as $node) {
                $this->upsert($node, null, $stats);
            }
        });
        CategoryTree::flush();

        return $stats;
    }

    /** @param array<string, mixed> $n */
    private function upsert(array $n, ?Category $parent, array &$stats): void
    {
        $path = $parent ? $parent->path.'/'.$n['slug'] : $n['slug'];
        $existing = Category::query()->where('path', $path)->first();
        $sources = (array) ($n['sources'] ?? []);
        if ($existing && ! empty($existing->sources['amazon']['node']) && isset($sources['amazon'])) {
            $sources['amazon']['node'] = $existing->sources['amazon']['node'];
        }
        $sort = (int) ($n['sort'] ?? 0);
        $attrs = [
            'market' => Category::UNIFIED, 'slug' => $n['slug'], 'path' => $path, 'name' => $n['name'], 'name_bn' => $n['name_bn'] ?? null,
            'icon' => $n['icon'] ?? null, 'sort' => $sort, 'parent_id' => $parent?->id, 'depth' => $parent ? $parent->depth + 1 : 1,
            'sort_key' => ($parent ? $parent->sort_key.'.' : '').sprintf('%04d', $sort),
            'pricing_group' => $n['pricing_group'] ?? null, 'sources' => $sources, 'keywords' => array_values($n['keywords'] ?? []),
            'featured' => (bool) ($n['featured'] ?? false),
        ];
        if ($existing) {
            $existing->fill($attrs);
            if ($existing->isDirty()) {
                $existing->saveQuietly();
                $stats['updated']++;
            }
            $cat = $existing;
        } else {
            $cat = new Category($attrs + ['active' => true, 'seed_pages' => 2]);
            $cat->saveQuietly();
            $stats['created']++;
        }
        $stats['total']++;
        foreach ($n['children'] ?? [] as $child) {
            $this->upsert($child, $cat, $stats);
        }
    }
}
