<?php

namespace App\Http\Controllers\Api\Growth;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Growth\ReferralService;
use App\Support\ProductJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Public wishlists: POST /wishlist/share (auth) makes a code, GET /wishlists/{code} lists the products (first name only). */
class SharedWishlistController extends Controller
{
    public function share(Request $request, ReferralService $referrals): JsonResponse
    {
        $user = $request->user();
        if (! $user->wishlist_code) {
            do {
                $code = Str::lower($referrals->randomCode(8));
            } while (User::query()->where('wishlist_code', $code)->exists());
            $user->forceFill(['wishlist_code' => $code])->saveQuietly();
        }
        $web = rtrim((string) config('retail.web_url'), '/');

        return response()->json(['code' => $user->wishlist_code, 'url' => "{$web}/w/{$user->wishlist_code}"]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $user = User::query()->where('wishlist_code', Str::lower($code))->whereNull('blocked_at')->first();
        abort_unless($user, 404, 'Wishlist not found.');
        $ids = WishlistItem::query()->where('user_id', $user->id)->latest('id')->limit(100)->pluck('product_id')->all();
        $products = Product::query()->with('details')->whereIn('id', $ids)->get()->keyBy('id');
        $items = collect($ids)->map(fn ($id) => $products[$id] ?? null)->filter()->values();

        return response()->json([
            'code' => $user->wishlist_code,
            'owner' => ['name' => explode(' ', trim($user->name))[0] ?: 'A shopper'],
            'count' => $items->count(),
            'items' => ProductJson::many($items, $request),
        ]);
    }
}
