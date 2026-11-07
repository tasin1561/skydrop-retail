<?php

namespace App\Sources\Support;

/**
 * A fully fetched product. $data uses the internal snake_case shape produced by the parsers:
 * id, market, title, brand, price_inr, mrp_inr, rating, reviews, image, images, prime, best_seller, amazons_choice,
 * sales_volume, in_stock, deliverable, stock_text, delivery_text, bullets, specs, weight, variants, videos, aplus,
 * review_list, star_breakdown, category_path, sales_rank, parent_id, offers, deal_badge, coupon_inr,
 * effective_price_inr, deal_ends_at.
 */
final class SourceProduct
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly array $data,
        public readonly string $provider,
        public readonly bool $pincodeVerified,
        public readonly ?string $rawHtml = null,
    ) {}
}
