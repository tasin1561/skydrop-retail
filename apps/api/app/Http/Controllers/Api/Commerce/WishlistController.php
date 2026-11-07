<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WishlistItem;
use App\Support\ProductJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ids = WishlistItem::query()->where('user_id', $request->user()->id)->latest('id')->pluck('product_id')->all();
        $products = Product::query()->with('details')->whereIn('id', $ids)->get()->keyBy('id');
        $ordered = collect($ids)->map(fn ($id) => $products[$id] ?? null)->filter()->values();

        return response()->json(['items' => ProductJson::many($ordered, $request)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['productId' => ['required', 'string', 'max:40', 'exists:products,id']]);
        WishlistItem::query()->firstOrCreate(['user_id' => $request->user()->id, 'product_id' => $data['productId']]);

        return response()->json(['ok' => true, 'productId' => $data['productId']], 201);
    }

    public function destroy(Request $request, string $productId): JsonResponse
    {
        WishlistItem::query()->where('user_id', $request->user()->id)->where('product_id', $productId)->delete();

        return response()->json(['ok' => true]);
    }
}
