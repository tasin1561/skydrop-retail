<?php

namespace App\Http\Controllers\Api\Shopping;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Product;
use App\Services\PricingService;
use App\Services\Shopping\BundleService;
use App\Support\ProductJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BundleController extends Controller
{
    /** GET products/{id}/bought-together?limit=3 */
    public function boughtTogether(Request $request, string $id, BundleService $bundles, PricingService $pricing): JsonResponse
    {
        $p = Product::query()->findOrFail($id);
        $r = $bundles->boughtTogether($p, (int) $request->query('limit', 3));
        $total = $pricing->productBdt($p) + array_sum(array_column($r['items'], 'priceBdt'));

        return response()->json(['product' => ProductJson::one($p)] + $r + ['totalBdt' => $total]);
    }

    /** GET products/{id}/bundles */
    public function forProduct(string $id, BundleService $bundles): JsonResponse
    {
        return response()->json(['items' => $bundles->forProduct($id)->map(fn ($b) => $bundles->toApi($b))->values()]);
    }

    /** GET bundles */
    public function index(BundleService $bundles): JsonResponse
    {
        return response()->json(['items' => Bundle::query()->live()->orderBy('sort')->orderByDesc('id')->limit(50)->get()
            ->map(fn ($b) => $bundles->toApi($b, false))->values()]);
    }

    /** GET bundles/{slug} */
    public function show(string $slug, BundleService $bundles): JsonResponse
    {
        return response()->json(['bundle' => $bundles->toApi(Bundle::query()->live()->where('slug', $slug)->firstOrFail())]);
    }
}
