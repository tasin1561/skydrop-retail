<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\UrlResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductController extends CatalogController
{
    public function show(Request $request, string $id, CatalogService $catalog): JsonResponse
    {
        try {
            $res = $catalog->product($id, $this->isCrawler($request));
        } catch (Throwable $e) {
            // Live fetch / queue / provider trouble: serve what we have in the database.
            report($e);
            $p = rescue(fn () => Product::query()->with('details')->find($id), null, false);
            if (! $p) {
                return $this->degraded('We could not load this product right now. Please try again in a moment.');
            }
            $res = ['status' => 'ready', 'stale' => true, 'product' => $p];
        }
        abort_if(! $res, 404, 'Product not found');
        if ($res['product'] === null) {
            // Not in our database yet: fetch queued. 202 + jobId; the client polls /jobs/{id}.
            return response()->json($res, 202);
        }
        try {
            $res['product'] = (new ProductResource($res['product']->loadMissing('details')))->resolve($request);
        } catch (Throwable $e) {
            // Broken detail payload (bad parse): fall back to the card fields so the page still renders.
            report($e);
            $res['product'] = (new ProductResource($res['product']->setRelation('details', null)))->resolve($request);
            $res['stale'] = true;
        }

        return response()->json($res);
    }

    /** GET /products?ids=a,b,c (cards, max 100, in the requested order; hidden products are included). */
    public function index(Request $request): JsonResponse
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $request->query('ids', '')))))), 0, 100);
        $rows = $ids ? Product::query()->whereIn('id', $ids)->get()->keyBy('id') : collect();

        return response()->json(['items' => $this->cards(collect($ids)->map(fn ($id) => $rows[$id] ?? null)->filter(), $request)]);
    }

    public function popular(Request $request, CatalogQuery $query): JsonResponse
    {
        $v = $request->validate(['store' => self::STORES, 'category' => 'nullable|string|max:40', 'limit' => 'nullable|integer|min:1|max:60', 'brand' => self::BRAND]);
        $brands = $this->brands($v['brand'] ?? null);

        return response()->json(['items' => $this->cards($query->popular($v['store'] ?? null, (int) ($v['limit'] ?? 12), $v['category'] ?? null, $brands), $request)]);
    }

    public function deals(Request $request, CatalogQuery $query): JsonResponse
    {
        $v = $request->validate(['store' => self::STORES, 'limit' => 'nullable|integer|min:1|max:60']);

        return response()->json(['items' => $this->cards($query->deals($v['store'] ?? null, (int) ($v['limit'] ?? 12)), $request)]);
    }

    public function related(Request $request, string $id, CatalogQuery $query): JsonResponse
    {
        $p = Product::query()->findOrFail($id);
        $v = $request->validate(['limit' => 'nullable|integer|min:1|max:40']);

        return response()->json(['items' => $this->cards($query->related($p, (int) ($v['limit'] ?? 8)), $request)]);
    }

    public function cancelVariants(string $id, CatalogService $catalog): JsonResponse
    {
        return response()->json(['ok' => true, 'pending' => $catalog->cancelVariants($id)]);
    }

    /** POST /products/resolve {url}: paste any amazon.in / flipkart.com / bigbasket.com / myntra.com link. */
    public function resolve(Request $request, UrlResolver $resolver, CatalogService $catalog): JsonResponse
    {
        $url = (string) $request->validate(['url' => 'required|string|max:2000'])['url'];
        $fail = fn (string $msg) => throw ValidationException::withMessages(['url' => [$msg]]);
        if (! $resolver->isSupportedHost($url)) {
            $fail('Paste a product link from amazon.in, flipkart.com, bigbasket.com or myntra.com.');
        }
        $found = $resolver->resolve($url);
        if (! $found) {
            $fail("We couldn't find a product in that link. Open the product page and copy its address.");
        }
        $id = $found['id'];
        if ($p = Product::query()->with('details')->find($id)) {
            $res = $catalog->product($id, $this->isCrawler($request));

            return response()->json(array_filter([
                'status' => $res['status'], 'jobId' => $res['jobId'] ?? null, 'productId' => $id, 'market' => $found['market'],
                'product' => (new ProductResource($res['product']->loadMissing('details')))->resolve($request),
            ], fn ($v) => $v !== null));
        }
        if ($this->isCrawler($request) || ! $catalog->isLive($found['market'])) {
            $fail('This store is not connected for live products yet. Please try again later.');
        }

        return response()->json(['status' => 'pending', 'jobId' => $catalog->queueProduct($id, 'paste-link'), 'productId' => $id, 'market' => $found['market']], 202);
    }
}
