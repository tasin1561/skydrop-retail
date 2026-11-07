<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Server copy of the cart for signed-in shoppers (used for abandoned-cart reminders and cross-device restore). */
class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $cart = Cart::query()->where('user_id', $request->user()->id)->first();

        return response()->json(['items' => $cart && ! $cart->converted_at ? $cart->items : [], 'updatedAt' => $cart?->updated_at?->toIso8601String()]);
    }

    /** PUT /cart { items: CartItem[] } — replaces the cart. Empty items clears it. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['present', 'array', 'max:50'],
            'items.*.productId' => ['required', 'string', 'max:64'],
            'items.*.market' => ['nullable', 'string', 'max:20'],
            'items.*.title' => ['nullable', 'string', 'max:300'],
            'items.*.image' => ['nullable', 'string', 'max:500'],
            'items.*.variant' => ['nullable', 'string', 'max:120'],
            'items.*.unitBdt' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        $userId = $request->user()->id;
        if ($data['items'] === []) {
            Cart::query()->where('user_id', $userId)->delete();

            return response()->json(['ok' => true, 'items' => []]);
        }
        $items = array_map(fn ($i) => array_intersect_key($i, array_flip(['productId', 'market', 'title', 'image', 'variant', 'unitBdt', 'qty'])), $data['items']);
        $total = (int) round(array_sum(array_map(fn ($i) => (float) ($i['unitBdt'] ?? 0) * (int) $i['qty'], $items)));
        $count = (int) array_sum(array_column($items, 'qty'));

        $cart = Cart::query()->firstOrNew(['user_id' => $userId]);
        $changed = ! $cart->exists || $cart->items != $items || $cart->converted_at;
        $cart->fill(['items' => $items, 'total_bdt' => $total, 'item_count' => $count]);
        if ($changed) {
            // A changed cart starts the reminder clock again.
            $cart->fill(['reminded_1h_at' => null, 'reminded_24h_at' => null, 'converted_at' => null]);
            $cart->updated_at = now();
        }
        $cart->save();

        return response()->json(['ok' => true, 'items' => $cart->items]);
    }
}
