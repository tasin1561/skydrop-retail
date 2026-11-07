<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductWatch;
use App\Services\PricingService;
use App\Support\Limits;
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
        $max = Limits::int('accounts.max_price_alerts'); // System → Limits
        $active = ProductWatch::query()->where('user_id', $request->user()->id)->where('active', true);
        if (! (clone $active)->where('product_id', $p->id)->where('type', $data['type'])->exists() && $active->count() >= $max) {
            $msg = "You can have up to {$max} active alerts. Remove one to add this.";

            return response()->json(['message' => $msg, 'code' => 'limit_alerts', 'errors' => ['productId' => [$msg]]], 422);
        }
        $watch = ProductWatch::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $p->id, 'type' => $data['type']],
            ['target_bdt' => $data['targetBdt'] ?? null, 'baseline_bdt' => $pricing->productBdt($p),
                'active' => true, 'notified_at' => null],
        );

        return response()->json(['watch' => $watch->toApi()], 201);
    }

    /** DELETE watches/{productId}?type=price_drop|back_in_stock (omit type to remove both). */
    public function destroy(Request $request, string $productId): JsonResponse
    {
        $type = $request->query('type');
        $n = ProductWatch::query()->where('user_id', $request->user()->id)->where('product_id', $productId)
            ->when(in_array($type, ['price_drop', 'back_in_stock'], true), fn ($q) => $q->where('type', $type))->delete();

        return response()->json(['ok' => true, 'removed' => $n]);
    }
}
