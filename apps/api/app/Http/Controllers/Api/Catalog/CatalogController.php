<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

/** Shared helpers for the catalog controllers. */
abstract class CatalogController extends Controller
{
    protected const STORES = 'nullable|in:all,amazon,flipkart,bigbasket';

    protected function isCrawler(Request $request): bool
    {
        return (bool) $request->attributes->get('crawler', false);
    }

    /** @param iterable<Product> $products */
    protected function cards(iterable $products, Request $request): array
    {
        return collect($products)->map(fn (Product $p) => (new ProductResource($p))->resolve($request))->values()->all();
    }
}
