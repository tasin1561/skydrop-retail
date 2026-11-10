<?php

namespace App\Services\Catalog;

use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\RawPage;
use App\Sources\Support\SourceProduct;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Writes provider results into products / product_details. Listing rows from every search refresh price, rating
 * and coupon for free; detailed fetches write everything, store the gzipped raw page and price history.
 * Unavailable or undeliverable products are hidden from listings but kept (product page stays for SEO).
 */
class ProductWriter
{
    public function __construct(private CategoryGuesser $guesser) {}

    /** @param array<string, mixed> $row */
    public function listing(array $row, string $source, bool $pincodeVerified, ?string $category = null): ?Product
    {
        if (! empty($row['stub']) || empty($row['id']) || (float) ($row['price_inr'] ?? 0) <= 0) {
            return null;
        }

        return DB::transaction(function () use ($row, $source, $pincodeVerified, $category) {
            /** @var Product $p */
            $p = Product::query()->lockForUpdate()->find($row['id']) ?? new Product(['id' => $row['id']]);
            $old = $p->exists ? (float) $p->price_inr : null;
            $price = (float) $row['price_inr'];
            $mrp = (float) ($row['mrp_inr'] ?? $price);
            if ($p->exists && $mrp <= $price && (float) $p->mrp_inr > $price) {
                $mrp = (float) $p->mrp_inr; // listing hid the strike-through price; keep the known MRP
            }
            $p->fill([
                'market' => $row['market'],
                'category' => $category ?: ($p->category ?: $this->guesser->guess($row['market'], (string) $row['title'])),
                'title' => mb_substr((string) $row['title'], 0, 500),
                'brand' => $row['brand'] ?? $p->brand,
                'price_inr' => $price,
                'mrp_inr' => max($mrp, $price),
                'rating' => (float) ($row['rating'] ?? 0) ?: $p->rating ?? 0,
                'reviews' => max((int) ($row['reviews'] ?? 0), $p->exists && ! $row['reviews'] ? (int) $p->reviews : 0),
                'image' => $p->image && $p->detailed ? $p->image : ($row['image'] ?? $p->image),
                'prime' => (bool) ($row['prime'] ?? false),
                'best_seller' => (bool) ($row['best_seller'] ?? false),
                'amazons_choice' => (bool) ($row['amazons_choice'] ?? false),
                'sales_volume' => ($row['sales_volume'] ?? '') ?: $p->sales_volume,
                'source' => $source,
                'pincode_verified' => $pincodeVerified,
                'price_checked_at' => now(),
                'demo' => false,
            ]);
            if (! $p->exists) {
                $p->in_stock = true;
                $p->hidden = false;
                $p->detailed = false;
            }
            $this->applyOffers($p, $row, keepDeal: true);
            $p->save();
            $this->recordPrice($p, $old);

            return $p;
        });
    }

    public function detailed(SourceProduct $sp, ?string $category = null): Product
    {
        $d = $sp->data;

        return DB::transaction(function () use ($d, $sp, $category) {
            /** @var Product $p */
            $p = Product::query()->lockForUpdate()->find($d['id']) ?? new Product(['id' => $d['id']]);
            $old = $p->exists ? (float) $p->price_inr : null;
            $price = (float) ($d['price_inr'] ?? 0);
            $inStock = (bool) ($d['in_stock'] ?? $price > 0);
            $deliverable = (bool) ($d['deliverable'] ?? true);
            $p->fill([
                'market' => $d['market'],
                'parent_id' => $d['parent_id'] ?? $p->parent_id,
                // A listing-only row's category was guessed from its title: the first product page brings the store's
                // breadcrumb, which is better (a TV titled "…Smart Google TV" is electronics, not the store default).
                'category' => $category ?: (($p->category && ($p->detailed || empty($d['category_path']) || $p->category_source === 'manual'))
                    ? $p->category : $this->guesser->guess($d['market'], (string) $d['title'], (array) ($d['category_path'] ?? []))),
                'title' => mb_substr((string) $d['title'], 0, 500),
                'brand' => ($d['brand'] ?? '') ?: $p->brand,
                // An unavailable page has no price: keep the last known one for display and history.
                'price_inr' => $price > 0 ? $price : ($p->price_inr ?? 0),
                'mrp_inr' => max((float) ($d['mrp_inr'] ?? 0), $price > 0 ? $price : (float) ($p->price_inr ?? 0)),
                'rating' => (float) ($d['rating'] ?? 0),
                'reviews' => (int) ($d['reviews'] ?? 0),
                'image' => $d['image'] ?? $p->image,
                'prime' => (bool) ($d['prime'] ?? false),
                'best_seller' => (bool) ($d['best_seller'] ?? false),
                'amazons_choice' => (bool) ($d['amazons_choice'] ?? false),
                'sales_volume' => (string) ($d['sales_volume'] ?? ''),
                'in_stock' => $inStock,
                'hidden' => ! $inStock || ! $deliverable,
                'detailed' => true,
                'demo' => false,
                'pincode_verified' => $sp->pincodeVerified,
                'source' => $sp->provider,
                'price_checked_at' => now(),
                'details_fetched_at' => now(),
            ]);
            $this->applyOffers($p, $d, keepDeal: false);
            $p->save();

            ProductDetail::query()->updateOrCreate(['product_id' => $p->id], [
                'images' => $d['images'] ?? [],
                'videos' => $d['videos'] ?? [],
                'bullets' => $d['bullets'] ?? [],
                'specs' => (object) ($d['specs'] ?? []),
                'variants' => $d['variants'] ?? [],
                'aplus' => $d['aplus'] ?? [],
                'reviews' => $d['review_list'] ?? [],
                'star_breakdown' => $d['star_breakdown'] ?? [],
                'category_path' => $d['category_path'] ?? [],
                'offers' => $d['offers'] ?? [],
                'weight' => $d['weight'] ?? null,
                'stock_text' => isset($d['stock_text']) ? mb_substr((string) $d['stock_text'], 0, 250) : null,
                'delivery_text' => isset($d['delivery_text']) ? mb_substr((string) $d['delivery_text'], 0, 250) : null,
                'has_videos' => ! empty($d['videos']),
            ]);
            if ($price > 0) {
                $this->recordPrice($p, $old, $inStock);
            }
            if ($sp->rawHtml !== null) {
                RawPage::query()->create(['product_id' => $p->id, 'html_gz' => gzencode($sp->rawHtml, 6), 'fetched_at' => now()]);
            }

            return $p;
        });
    }

    /** The store says the page no longer exists: hide it, keep the row. */
    public function gone(string $id): void
    {
        Product::query()->whereKey($id)->update(['in_stock' => false, 'hidden' => true, 'price_checked_at' => now(), 'details_fetched_at' => now()]);
        ProductDetail::query()->whereKey($id)->update(['stock_text' => 'Currently unavailable']);
    }

    /**
     * Offer columns. A listing never clears a deal badge it cannot see; a detail page is authoritative.
     *
     * @param  array<string, mixed>  $d
     */
    private function applyOffers(Product $p, array $d, bool $keepDeal): void
    {
        $price = (float) $p->price_inr;
        $coupon = isset($d['coupon_inr']) ? (float) $d['coupon_inr'] : null;
        $p->coupon_inr = $coupon ?: null;
        $p->effective_price_inr = $d['effective_price_inr'] ?? ($coupon ? max(1, $price - $coupon) : null);
        if (! $keepDeal || ! empty($d['deal_badge'])) {
            $p->deal_badge = $d['deal_badge'] ?? null;
            $p->deal_ends_at = ! empty($d['deal_ends_at']) ? CarbonImmutable::parse($d['deal_ends_at']) : null;
        }
        $buy = $p->effective_price_inr && $p->effective_price_inr < $price ? (float) $p->effective_price_inr : $price;
        $p->saving_inr = max(0, max((float) $p->mrp_inr, $price) - $buy);
    }

    private function recordPrice(Product $p, ?float $old, bool $inStock = true): void
    {
        if ($old === null || abs($old - (float) $p->price_inr) >= 0.01) {
            PriceHistory::query()->create([
                'product_id' => $p->id, 'price_inr' => $p->price_inr, 'in_stock' => $inStock, 'recorded_at' => now(),
            ]);
        }
    }
}
