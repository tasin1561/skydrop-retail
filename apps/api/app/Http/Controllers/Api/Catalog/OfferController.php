<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Services\Catalog\CatalogQuery;
use App\Sources\Support\Offers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends CatalogController
{
    /** GET /offers?store=&type=&limit= : products with live coupons / deal prices, biggest saving first. */
    public function index(Request $request, CatalogQuery $query): JsonResponse
    {
        $v = $request->validate(['store' => self::STORES, 'type' => 'nullable|in:'.implode(',', Offers::TYPES), 'limit' => 'nullable|integer|min:1|max:60']);

        return response()->json(['items' => $this->cards($query->offers($v['store'] ?? null, $v['type'] ?? null, (int) ($v['limit'] ?? 24)), $request)]);
    }
}
