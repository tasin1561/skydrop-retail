<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Services\Catalog\CatalogQuery;
use App\Services\Categories\CategoryTree;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Unified category tree: GET /categories and GET /categories/{path} (docs/API.md "Categories"). */
class CategoryController extends CatalogController
{
    public function index(Request $request, CategoryTree $tree): JsonResponse
    {
        $v = $request->validate(['store' => self::STORES, 'depth' => 'nullable|integer|min:1|max:4']);
        $store = $v['store'] ?? 'all';

        return response()->json(['store' => $store, 'items' => $tree->tree($store, isset($v['depth']) ? (int) $v['depth'] : null)])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function show(Request $request, string $path, CategoryTree $tree, CatalogQuery $query, PricingService $pricing): JsonResponse
    {
        $v = $request->validate(['store' => self::STORES]);
        $store = $v['store'] ?? 'all';
        $node = $tree->byPath($path);
        if (! $node) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        $parent = $node['parent_id'] ? $tree->find($node['parent_id']) : null;
        $siblingIds = $parent ? $parent['children'] : $tree->roots();
        $pick = fn (array $ids) => array_values(array_map(fn ($id) => $tree->present($tree->find($id), $store),
            array_filter($ids, fn ($id) => CategoryTree::storeOk($tree->find($id), $store))));

        $stats = Cache::remember('categories:stats:'.$node['id'].':'.$store, 600, function () use ($tree, $query, $node, $store, $pricing) {
            $base = fn () => $query->visible($store)->whereIn('category_id', $tree->subtree($node['id']));
            $brands = $base()->whereNotNull('brand')->where('brand', '!=', '')
                ->select('brand', DB::raw('count(*) as n'))->groupBy('brand')->orderByDesc('n')->orderBy('brand')->limit(12)->get()
                ->map(fn ($r) => ['name' => $r->brand, 'count' => (int) $r->n])->all();
            $cheap = $base()->where('price_inr', '>', 0)->orderBy('price_inr')->first();
            $dear = $base()->where('price_inr', '>', 0)->orderByDesc('price_inr')->first();

            return [
                'brands' => $brands,
                'priceRange' => $cheap && $dear ? ['minBdt' => $pricing->productBdt($cheap), 'maxBdt' => $pricing->productBdt($dear)] : null,
            ];
        });

        return response()->json([
            'store' => $store,
            'node' => $tree->present($node, $store) + ['sources' => array_keys($node['sources'])],
            'breadcrumbs' => array_map(fn ($n) => ['path' => $n['path'], 'name' => $n['name'], 'nameBn' => $n['name_bn']], $tree->ancestry($node['id'])),
            'children' => $pick($node['children']),
            'siblings' => $pick(array_values(array_diff($siblingIds, [$node['id']]))),
            'brands' => $stats['brands'],
            'priceRange' => $stats['priceRange'],
            'available' => CategoryTree::storeOk($node, $store),
        ])->header('Cache-Control', 'public, max-age=120');
    }
}
