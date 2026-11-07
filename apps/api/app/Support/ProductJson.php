<?php

namespace App\Support;

use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Product JSON for commerce endpoints. Uses the catalog module's App\Http\Resources\ProductResource when present,
 * otherwise a minimal card in the same camelCase shape.
 */
final class ProductJson
{
    public const RESOURCE = 'App\\Http\\Resources\\ProductResource';

    /** @param iterable<Product> $products */
    public static function many(iterable $products, ?Request $request = null): array
    {
        $request ??= request();

        return collect($products)->map(fn (Product $p) => self::one($p, $request))->values()->all();
    }

    public static function one(Product $p, ?Request $request = null): array
    {
        if (class_exists(self::RESOURCE)) {
            $class = self::RESOURCE;

            return (new $class($p))->resolve($request ?? request());
        }
        $pricing = app(PricingService::class);

        return [
            'id' => $p->id, 'market' => $p->market, 'category' => $p->category, 'title' => $p->title, 'brand' => (string) $p->brand,
            'priceInr' => (float) $p->price_inr, 'mrpInr' => (float) $p->mrp_inr,
            'priceBdt' => $pricing->priceBdt((float) $p->price_inr, (string) $p->category),
            'mrpBdt' => $pricing->priceBdt((float) ($p->mrp_inr ?: $p->price_inr), (string) $p->category),
            'rating' => (float) $p->rating, 'reviews' => (int) $p->reviews, 'image' => $p->image,
            'images' => $p->details?->images ? (array) json_decode(json_encode($p->details->images), true) : array_values(array_filter([$p->image])),
            'placeholder' => $p->placeholder, 'prime' => (bool) $p->prime, 'bestSeller' => (bool) $p->best_seller,
            'amazonsChoice' => (bool) $p->amazons_choice, 'salesVolume' => (string) $p->sales_volume, 'detailed' => (bool) $p->detailed,
            'demo' => (bool) $p->demo, 'stock' => $p->in_stock ? 'In stock' : 'Out of stock',
        ];
    }
}
