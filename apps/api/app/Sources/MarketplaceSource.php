<?php

namespace App\Sources;

use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;

/**
 * A provider that can read one marketplace. Implementations normalise everything into
 * SearchPage (listing rows) and SourceProduct (full detail) so the catalog never sees provider shapes.
 */
interface MarketplaceSource
{
    /** Stable provider key used in provider_health and api_logs, e.g. "oxylabs", "omkar". */
    public function name(): string;

    public function market(): string;

    /** False when credentials are missing; the chain skips it. */
    public function isConfigured(): bool;

    /** True when results reflect the Kolkata receiving pincode (price, stock and delivery). */
    public function supportsPincode(): bool;

    /** True when every call is billed against the Oxylabs budget. */
    public function isMetered(): bool;

    /** Return a copy that fires a duplicate request when the first is slow (interactive fetches). */
    public function withHedging(bool $on = true): static;

    public function search(string $query, int $page = 1, string $sort = 'relevance', ?string $category = null): SearchPage;

    public function product(string $id): SourceProduct;

    /**
     * Fetch many products concurrently where the provider allows it.
     *
     * @param  list<string>  $ids
     * @return array<string, SourceProduct|\Throwable>
     */
    public function products(array $ids): array;

    public function bestsellers(string $categoryId, int $page = 1): SearchPage;

    /** Listings from the store's deals / offers page. */
    public function deals(int $page = 1): SearchPage;
}
