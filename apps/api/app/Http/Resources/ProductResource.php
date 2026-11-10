<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\Categories\CategoryTree;
use App\Services\PricingService;
use App\Support\ProductJson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Product JSON in the web `Product` shape (apps/web/src/lib/types.ts), camelCase, plus priceBdt/mrpBdt,
 * offers and freshness fields. Detail fields are included only when the details relation is loaded.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Product $p */
        $p = $this->resource;
        $pricing = app(PricingService::class);
        $d = $p->relationLoaded('details') ? $p->details : null;
        $images = $d?->images ?: array_values(array_filter([$p->image]));

        $out = [
            'id' => $p->id,
            'market' => $p->market,
            'category' => $p->category,
            'categoryTrail' => $this->trail($p), // the product's own category node and its parents (breadcrumb)
            'title' => $p->title,
            'brand' => (string) $p->brand,
            'priceInr' => (float) $p->price_inr,
            'mrpInr' => (float) max($p->mrp_inr, $p->price_inr),
            'priceBdt' => $pricing->productBdt($p),
            'mrpBdt' => $pricing->priceBdt((float) max($p->mrp_inr, $p->price_inr), (string) $p->category),
            'rating' => (float) $p->rating,
            'reviews' => (int) $p->reviews,
            'image' => $p->image,
            'images' => array_values($images),
            'prime' => (bool) $p->prime,
            'bestSeller' => (bool) $p->best_seller,
            'amazonsChoice' => (bool) $p->amazons_choice,
            'salesVolume' => (string) $p->sales_volume,
            'detailed' => (bool) $p->detailed,
            'inStock' => (bool) $p->in_stock,
            'dealBadge' => $p->deal_badge,
            'couponInr' => $p->coupon_inr !== null ? (float) $p->coupon_inr : null,
            'effectivePriceInr' => $p->effective_price_inr !== null ? (float) $p->effective_price_inr : null,
            'dealEndsAt' => $p->deal_ends_at?->toIso8601String(),
            'fetchedAt' => ($p->details_fetched_at ?? $p->price_checked_at)?->toIso8601String(),
            'pincodeVerified' => (bool) $p->pincode_verified,
        ];
        if ($p->placeholder) {
            $out['placeholder'] = $p->placeholder;
        }
        if ($p->demo) {
            $out['demo'] = true;
        }
        if ($d) {
            $out += [
                'stock' => $d->stock_text ?? ($p->in_stock ? 'In stock' : 'Currently unavailable'),
                'bullets' => $d->bullets ?? [],
                'specs' => (object) ($d->specs ?? []),
                'weight' => $d->weight,
                'variants' => $this->variants($p, $d->variants ?? []),
                'aplus' => $d->aplus ?? [],
                'hasVideos' => (bool) $d->has_videos,
                'videos' => $d->videos ?? [],
                'reviewList' => $d->reviews ?? [],
                'starBreakdown' => $d->star_breakdown ?? [],
                'deliveryKolkata' => $d->delivery_text,
                'categoryPath' => $d->category_path ?? [],
                'offers' => $this->offers($d->offers ?? [], $p),
            ];
        } else {
            $out['offers'] = [];
        }
        $out += ProductJson::cms($p); // flashSale + restriction (CMS module)

        return $out;
    }

    /**
     * Variants keep {id,label,dimensions,swatch,selected} and add "available": false only when we know the
     * sibling is out of stock or undeliverable.
     *
     * @param  list<array<string, mixed>>  $variants
     * @return list<array<string, mixed>>
     */
    /** @return list<array{path: string, name: string, nameBn: ?string}> */
    private function trail(Product $p): array
    {
        if (! $p->category_id) {
            return [];
        }

        return array_map(fn (array $n) => ['path' => $n['path'], 'name' => $n['name'], 'nameBn' => $n['name_bn'] ?? null],
            app(CategoryTree::class)->ancestry((int) $p->category_id));
    }

    private function variants(Product $p, array $variants): array
    {
        $ids = array_values(array_filter(array_column($variants, 'id')));
        $known = $ids ? Product::query()->whereIn('id', $ids)->get(['id', 'in_stock', 'hidden'])->keyBy('id') : collect();

        return array_map(function (array $v) use ($known, $p) {
            $s = $v['id'] ? ($known[$v['id']] ?? null) : null;

            return [
                'id' => $v['id'] ?? null,
                'label' => (string) ($v['label'] ?? ''),
                'dimensions' => (object) ($v['dimensions'] ?? []),
                'swatch' => $v['swatch'] ?? null,
                'selected' => (bool) ($v['selected'] ?? ($v['id'] ?? null) === $p->id),
                'available' => $s ? ($s->in_stock && ! $s->hidden) : (bool) ($v['in_stock'] ?? true), // stored row first, else the page's own stock flag
            ];
        }, $variants);
    }

    /**
     * Drop timed offers that already ended.
     *
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    private function offers(array $offers, Product $p): array
    {
        return array_values(array_filter($offers, fn ($o) => empty($o['endsAt']) || strtotime((string) $o['endsAt']) > time()));
    }
}
