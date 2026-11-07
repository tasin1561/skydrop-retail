<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\FlashSale;
use App\Models\Product;
use App\Services\Cms\FlashSaleService;
use App\Services\PricingService;
use App\Support\Features;
use App\Support\ProductJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsController extends Controller
{
    /**
     * Home / store page content: live banners and live + upcoming flash sales with products.
     * store=all (default) returns everything; a marketplace returns its own items plus store "all" ones.
     */
    public function home(Request $request, FlashSaleService $flash, PricingService $pricing): JsonResponse
    {
        $store = in_array($request->query('store'), Banner::STORES, true) ? $request->query('store') : 'all';
        $forStore = fn ($q) => $store === 'all' ? $q : $q->whereIn('store', ['all', $store]);

        $banners = Features::enabled('banners')
            ? $forStore(Banner::query()->live())->orderBy('sort')->orderByDesc('id')->limit(30)->get() : collect();

        $sales = ! Features::enabled('flash_sales') ? collect() : $forStore(FlashSale::query()->with('items')->where('active', true)->where('ends_at', '>', now())
            ->where('starts_at', '<=', now()->addDays(7)))->orderBy('starts_at')->limit(6)->get();
        $ids = $sales->flatMap(fn (FlashSale $s) => $s->items->pluck('product_id'))->unique()->values()->all();
        $products = Product::query()->whereIn('id', $ids)->where('hidden', false)->get()->keyBy('id');

        $saleJson = $sales->map(function (FlashSale $s) use ($products, $flash, $pricing, $request) {
            $items = [];
            foreach ($s->items as $it) {
                $p = $products[$it->product_id] ?? null;
                if (! $p || ($s->store !== 'all' && $p->market !== $s->store)) {
                    continue;
                }
                $regular = $pricing->basePriceBdt($p);
                $off = $flash->amount(['offPct' => $it->off_pct, 'offBdt' => $it->off_bdt], $regular, (string) $p->category);
                $items[] = ['product' => ProductJson::one($p, $request), 'regularBdt' => $regular, 'extraOffBdt' => $off, 'flashBdt' => max(0, $regular - $off)];
            }

            return [
                'id' => (string) $s->id, 'title' => $s->title, 'store' => $s->store, 'status' => $s->status(),
                'startsAt' => $s->starts_at->toIso8601String(), 'endsAt' => $s->ends_at->toIso8601String(), 'maxQty' => $s->max_qty,
                'items' => $items,
            ];
        })->filter(fn ($s) => $s['items'])->values();

        return response()->json([
            'store' => $store,
            'enabled' => ['banners' => Features::enabled('banners'), 'flashSales' => Features::enabled('flash_sales')],
            'banners' => $banners->map->toApi()->values(),
            'flashSales' => $saleJson,
            'serverTime' => now()->toIso8601String(),
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
