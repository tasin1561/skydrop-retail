<?php

namespace App\Sources\Support;

/**
 * One page of listing results, already normalised.
 *
 * Each item: id, market, title, brand?, price_inr, mrp_inr, rating, reviews, image?, prime, best_seller,
 * amazons_choice, sales_volume?, coupon_inr?, offers?, deal_badge?, category?
 */
final class SearchPage
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total = 0,
        public readonly int $pages = 1,
        public readonly string $provider = '',
        public readonly bool $pincodeVerified = false,
    ) {}

    public function withProvider(string $provider, bool $pincodeVerified): self
    {
        return new self($this->items, $this->total, $this->pages, $provider, $pincodeVerified);
    }
}
