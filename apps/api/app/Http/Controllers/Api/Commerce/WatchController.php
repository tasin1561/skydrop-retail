<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductWatch;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = ProductWatch::query()->where('user_id', $request->user()->id)->latest('id')->get();

        return response()->json(['items' => $items->map->toApi()->values()]);
    }

    public function store(Request $request, PricingService $pricing): JsonResponse
    {
        $data = $request->validate([
            'productId' => ['required', 'string', 'max:40', 'exists:products,id'],
            'type' => ['required', Rule::in(['price_drop', 'back_in_stock'])],
            'targetBdt' => ['nullable', 'integer', 'min:1'],
        ]);
        $p = Product::query()->findOrFail($data['productId']);
        $watch = ProductWatch::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $p->id, 'type' => $data['type']],
            ['target_bdt' => $data['targetBdt'] ?? null, 'baseline_bdt' => $pricing->priceBdt((float) $p->price_inr, (string) $p->category),
                'active' => true, 'notified_at' => null],
        );

        return response()->json(['watch' => $watch->toApi()], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        ProductWatch::query()->where('user_id', $request->user()->id)->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }
}
