<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Catalog\CatalogQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Shared helpers for the catalog controllers. */
abstract class CatalogController extends Controller
{
    protected const STORES = 'nullable|in:all,amazon,flipkart,myntra';

    /** ?brand=a,b: one or more brands, comma-separated (validated as a string first; see brands()). */
    protected const BRAND = 'nullable|string|max:700';

    /**
     * Parsed ?brand= list (validated). 422 when there are more than CatalogQuery::MAX_BRANDS names or one is too long.
     *
     * @return list<string>
     */
    protected function brands(?string $raw): array
    {
        $brands = CatalogQuery::parseBrands($raw);
        if ($brands === null) {
            throw ValidationException::withMessages(['brand' => [sprintf(
                'Pick up to %d brands, each at most %d characters.', CatalogQuery::MAX_BRANDS, CatalogQuery::BRAND_MAX_LEN,
            )]]);
        }

        return $brands;
    }

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
