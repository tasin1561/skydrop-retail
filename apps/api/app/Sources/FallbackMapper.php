<?php

namespace App\Sources;

use App\Sources\Support\Offers;
use App\Sources\Support\Text;

/**
 * Normalises the JSON of the Amazon fallback APIs (Omkar, OpenWebNinja). Both use similar, loosely documented
 * field names, so every field is looked up under its known aliases.
 */
final class FallbackMapper
{
    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>|null
     */
    public static function listing(array $r): ?array
    {
        $id = (string) self::pick($r, ['asin', 'product_id', 'id']);
        $price = self::money(self::pick($r, ['product_price', 'price', 'current_price']));
        $title = Text::clean((string) self::pick($r, ['product_title', 'title', 'name']));
        if ($id === '' || ! $price || $title === '') {
            return null;
        }
        $mrp = self::money(self::pick($r, ['product_original_price', 'original_price', 'list_price', 'price_strikethrough'])) ?? $price;
        $coupon = self::pick($r, ['coupon_text', 'coupon']);
        $couponInr = is_string($coupon) ? Offers::couponValue($coupon, $price) : null;

        return [
            'id' => $id, 'market' => 'amazon', 'title' => $title,
            'brand' => Text::clean((string) self::pick($r, ['brand', 'product_brand'])) ?: null,
            'price_inr' => $price, 'mrp_inr' => max($mrp, $price),
            'rating' => (float) self::pick($r, ['product_star_rating', 'rating', 'stars']),
            'reviews' => Text::count(self::pick($r, ['product_num_ratings', 'reviews', 'ratings_count', 'reviews_count'])),
            'image' => self::pick($r, ['product_photo', 'image', 'main_image', 'thumbnail']),
            'prime' => (bool) self::pick($r, ['is_prime', 'prime']),
            'best_seller' => (bool) self::pick($r, ['is_best_seller', 'best_seller']),
            'amazons_choice' => (bool) self::pick($r, ['is_amazon_choice', 'amazons_choice']),
            'sales_volume' => (string) self::pick($r, ['sales_volume', 'bought_past_month']),
            'coupon_inr' => $couponInr,
            'effective_price_inr' => Offers::effectivePrice($price, $couponInr),
            'offers' => $couponInr ? [Offers::make('coupon', (string) $coupon, null, $couponInr)] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    public static function product(string $id, array $d): array
    {
        $base = self::listing(['asin' => $id] + $d) ?? ['id' => $id, 'market' => 'amazon', 'title' => '', 'price_inr' => 0.0, 'mrp_inr' => 0.0, 'offers' => []];
        $images = array_values(array_filter(array_map(fn ($i) => is_array($i) ? ($i['hi_res'] ?? $i['large'] ?? $i['url'] ?? null) : $i,
            (array) self::pick($d, ['product_photos', 'images', 'photos']))));
        $stock = (string) self::pick($d, ['product_availability', 'availability', 'stock']);
        $specs = [];
        foreach ([self::pick($d, ['product_details', 'details']), self::pick($d, ['product_information', 'overview'])] as $block) {
            foreach ((array) $block as $k => $v) {
                if (is_scalar($v) && ! is_int($k)) {
                    $specs[Text::label((string) $k)] = Text::clean((string) $v);
                } elseif (is_array($v) && isset($v['name'], $v['value'])) {
                    $specs[Text::label((string) $v['name'])] = Text::clean((string) $v['value']);
                }
            }
        }
        $stars = [];
        foreach ((array) self::pick($d, ['rating_distribution', 'star_distribution']) as $k => $v) {
            $stars[] = ['rating' => (int) $k, 'percentage' => (int) Text::money((string) $v)];
        }
        $weight = null;
        foreach ($specs as $k => $v) {
            if (stripos($k, 'weight') !== false) {
                $weight = $v;
                break;
            }
        }
        $price = (float) ($base['price_inr'] ?? 0);

        return $base + [
            'images' => $images ?: array_values(array_filter([$base['image'] ?? null])),
            'in_stock' => $price > 0 && ! Text::isUnavailable($stock),
            'deliverable' => true,
            'stock_text' => $stock ?: null,
            'delivery_text' => (string) self::pick($d, ['delivery', 'delivery_info']) ?: null,
            'bullets' => array_values(array_map(fn ($b) => Text::clean((string) $b), array_filter((array) self::pick($d, ['about_product', 'bullets', 'feature_bullets']), 'is_string'))),
            'specs' => $specs,
            'weight' => $weight,
            'variants' => [],
            'videos' => [],
            'has_videos' => false,
            'aplus' => [],
            'review_list' => [],
            'star_breakdown' => $stars,
            'category_path' => array_values(array_filter(array_map(fn ($c) => is_array($c) ? ($c['name'] ?? null) : $c, (array) self::pick($d, ['category_path', 'categories'])), 'is_string')),
            'deal_badge' => null,
            'deal_ends_at' => null,
        ];
    }

    /** @param array<string, mixed> $r @param list<string> $keys */
    private static function pick(array $r, array $keys): mixed
    {
        foreach ($keys as $k) {
            if (isset($r[$k]) && $r[$k] !== '') {
                return $r[$k];
            }
        }

        return null;
    }

    private static function money(mixed $v): ?float
    {
        if (is_array($v)) {
            $v = $v['value'] ?? $v['amount'] ?? $v['current'] ?? null;
        }

        return Text::money($v);
    }
}
