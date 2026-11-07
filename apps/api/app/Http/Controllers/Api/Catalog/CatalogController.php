<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** Shared helpers for the catalog controllers. */
abstract class CatalogController extends Controller
{
    protected const STORES = 'nullable|in:all,amazon,flipkart,bigbasket';

    protected function isCrawler(Request $request): bool
    {
        return (bool) $request->attributes->get('crawler', false);
    }

    /**
     * Product cards. One malformed product never breaks a whole list: it is reported and skipped.
     *
     * @param  iterable<Product>  $products
     */
    protected function cards(iterable $products, Request $request): array
    {
        $out = [];
        foreach ($products as $p) {
            try {
                $out[] = (new ProductResource($p))->resolve($request);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $out;
    }

    /** JSON for "temporarily unavailable" — clients show a retry state instead of an error page. */
    protected function degraded(string $message, int $status = 503): JsonResponse
    {
        return response()->json(['status' => 'unavailable', 'message' => $message, 'retryAfter' => 10], $status, ['Retry-After' => '10']);
    }
}
