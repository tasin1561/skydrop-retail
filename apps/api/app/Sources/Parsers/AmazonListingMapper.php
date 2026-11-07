<?php

namespace App\Sources\Parsers;

use App\Sources\Support\Offers;
use App\Sources\Support\SearchPage;
use App\Sources\Support\Text;

/** Maps Oxylabs parse:true amazon_search / amazon_bestsellers content into listing rows. */
class AmazonListingMapper
{
    /** @param array<string, mixed> $content results[0].content */
    public function search(array $content): SearchPage
    {
        $res = $content['results'] ?? [];
        $rows = [];
        // Organic first (store relevance order), then Amazon's Choice; sponsored rows are skipped.
        foreach (['organic', 'amazons_choices'] as $section) {
            foreach ((array) ($res[$section] ?? []) as $r) {
                if (! empty($r['is_sponsored']) || empty($r['asin']) || isset($rows[$r['asin']])) {
                    continue;
                }
                $row = $this->row($r);
                if ($row) {
                    $rows[$r['asin']] = $row;
                }
            }
        }

        return new SearchPage(array_values($rows), (int) ($content['total_results_count'] ?? count($rows)), (int) ($content['last_visible_page'] ?? 1));
    }

    /** @param array<string, mixed> $content */
    public function bestsellers(array $content): SearchPage
    {
        $rows = [];
        foreach ((array) ($content['results'] ?? []) as $r) {
            $row = $this->row([
                'asin' => $r['asin'] ?? null, 'title' => $r['title'] ?? '', 'price' => $r['price'] ?? 0,
                'rating' => $r['rating'] ?? 0, 'reviews_count' => $r['ratings_count'] ?? 0,
                'url_image' => $r['image_url'] ?? null, 'best_seller' => ($r['pos'] ?? 99) <= 3,
            ]);
            if ($row) {
                $row['rank'] = (int) ($r['pos'] ?? 0);
                $rows[] = $row;
            }
        }

        return new SearchPage($rows, count($rows), (int) ($content['pages'] ?? 1));
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>|null
     */
    public function row(array $r): ?array
    {
        $price = (float) ($r['price'] ?? 0);
        if (empty($r['asin']) || $price <= 0 || empty($r['title'])) {
            return null;
        }
        $mrp = (float) ($r['price_strikethrough'] ?? 0);
        $offers = [];
        $coupon = null;
        if (! empty($r['coupon_discount'])) {
            $v = (float) $r['coupon_discount'];
            $coupon = ($r['coupon_discount_type'] ?? '') === 'percentage' ? round($price * $v / 100, 2) : $v;
            $title = ($r['coupon_discount_type'] ?? '') === 'percentage' ? "Save {$v}% with coupon" : 'Apply ₹'.number_format($v).' coupon';
            $offers[] = Offers::make('coupon', $title, null, $coupon);
        }
        foreach ((array) ($r['deals_discounts'] ?? []) as $deal) {
            $label = is_array($deal) ? (string) ($deal['label'] ?? $deal['type'] ?? 'Limited time deal') : (string) $deal;
            $offers[] = Offers::make(Offers::classify($label) === 'lightning' ? 'lightning' : 'deal', $label ?: 'Limited time deal', null, $mrp > $price ? $mrp - $price : null);
        }

        return [
            'id' => (string) $r['asin'],
            'market' => 'amazon',
            'title' => Text::clean((string) $r['title']),
            'brand' => Text::clean((string) ($r['manufacturer'] ?? '')) ?: null,
            'price_inr' => $price,
            'mrp_inr' => max($mrp, $price),
            'rating' => (float) ($r['rating'] ?? 0),
            'reviews' => (int) ($r['reviews_count'] ?? 0),
            'image' => $r['url_image'] ?? null,
            'prime' => (bool) ($r['is_prime'] ?? false),
            'best_seller' => (bool) ($r['best_seller'] ?? false),
            'amazons_choice' => (bool) ($r['is_amazons_choice'] ?? false),
            'sales_volume' => (string) ($r['sales_volume'] ?? ''),
            'delivery_text' => $r['shipping_information'] ?? null,
            'coupon_inr' => $coupon,
            'effective_price_inr' => Offers::effectivePrice($price, $coupon),
            'offers' => $offers,
            'deal_badge' => collect($offers)->whereIn('type', ['deal', 'lightning'])->first()['title'] ?? null,
        ];
    }
}
