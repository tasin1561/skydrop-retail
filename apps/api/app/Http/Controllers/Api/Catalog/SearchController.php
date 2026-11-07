<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends CatalogController
{
    public function search(Request $request, CatalogService $catalog): JsonResponse
    {
        $v = $request->validate([
            'q' => 'nullable|string|max:200', 'store' => self::STORES, 'category' => 'nullable|string|max:40',
            'sort' => 'nullable|in:relevance,price_asc,price_desc,rating,discount', 'min' => 'nullable|integer|min:0',
            'max' => 'nullable|integer|min:0', 'rating' => 'nullable|numeric|min:0|max:5', 'fast' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1|max:200',
        ]);
        $res = $catalog->search($v + ['fast' => $request->boolean('fast')], $this->isCrawler($request));
        $res['items'] = $this->cards($res['items'], $request);

        return response()->json($res);
    }

    public function suggest(Request $request, CatalogQuery $query): JsonResponse
    {
        $v = $request->validate(['q' => 'nullable|string|max:200', 'store' => self::STORES, 'limit' => 'nullable|integer|min:1|max:20']);

        return response()->json(['items' => $query->suggest((string) ($v['q'] ?? ''), $v['store'] ?? null, (int) ($v['limit'] ?? 6))]);
    }
}
