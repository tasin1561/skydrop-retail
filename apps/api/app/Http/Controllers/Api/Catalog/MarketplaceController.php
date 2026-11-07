<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Models\Marketplace;
use Illuminate\Http\JsonResponse;

class MarketplaceController extends CatalogController
{
    public function index(): JsonResponse
    {
        return response()->json(['items' => Marketplace::query()->orderByRaw("CASE id WHEN 'amazon' THEN 1 WHEN 'flipkart' THEN 2 ELSE 3 END")->get()
            ->map(fn (Marketplace $m) => ['id' => $m->id, 'name' => $m->name, 'source' => $m->source, 'enabled' => $m->enabled, 'liveFetch' => $m->live_fetch])]);
    }
}
