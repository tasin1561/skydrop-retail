<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\Commerce\CheckoutService;
use App\Support\Features;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Storefront promo coupons ("Show as promo" in Admin → Sales → Coupons): active, in schedule, uses left, open to everyone.
     * store=amazon|flipkart|bigbasket returns that store's coupons plus all-store ones. Feature `coupons` off → [].
     */
    public function featured(Request $request): JsonResponse
    {
        if (! Features::enabled('coupons')) {
            return response()->json(['items' => []]);
        }
        $store = in_array($request->query('store'), Coupon::STORES, true) ? $request->query('store') : 'all';
        $items = Coupon::query()->live()->where('featured', true)
            ->when($store !== 'all', fn ($q) => $q->whereIn('store', ['all', $store]))
            ->orderByRaw('expires_at is null')->orderBy('expires_at')->orderByDesc('id')->limit(10)->get()
            ->filter(fn (Coupon $c) => $c->phoneList() === []) // phone-restricted coupons are private
            ->map(fn (Coupon $c) => CheckoutService::couponApi($c))->values();

        return response()->json(['items' => $items]);
    }
}
