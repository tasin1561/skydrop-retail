<?php

namespace App\Sources\Parsers;

use App\Sources\Support\Offers;
use App\Sources\Support\SearchPage;
use App\Sources\Support\Text;

/**
 * Best-effort parser for bigbasket.com pages (Next.js): reads __NEXT_DATA__ product objects
 * ({id, desc, brand, pricing.discount.{mrp, prim_price.sp, d_text}, images, w, tags}) and JSON-LD.
 * Our product ids are "bb" + the BigBasket product id.
 */
class BigBasketParser
{
    /** @return array<string, mixed> */
    public function product(string $html, string $id): array
    {
        $doc = new HtmlDocument($html);
        $state = $doc->embeddedState();
        $bbId = preg_replace('/^bb/i', '', $id);
        $items = HtmlDocument::find($state, fn ($n) => isset($n['desc'], $n['pricing']) && (string) ($n['id'] ?? '') === $bbId, 1)
            ?: HtmlDocument::find($state, fn ($n) => isset($n['desc'], $n['pricing']), 1);
        $row = $items ? $this->row($items[0]) : null;
        $ld = $doc->jsonLdOfType('Product') ?? [];

        if (! $row) {
            $offer = (array) ($ld['offers'] ?? []);
            $offer = array_is_list($offer) ? ($offer[0] ?? []) : $offer;
            $price = Text::money($offer['price'] ?? null) ?? 0.0;
            $row = [
                'id' => $id, 'market' => 'bigbasket', 'title' => Text::clean((string) ($ld['name'] ?? $doc->text('//h1'))),
                'brand' => Text::clean(is_array($ld['brand'] ?? null) ? (string) ($ld['brand']['name'] ?? '') : (string) ($ld['brand'] ?? '')),
                'price_inr' => $price, 'mrp_inr' => $price, 'rating' => (float) ($ld['aggregateRating']['ratingValue'] ?? 0),
                'reviews' => (int) ($ld['aggregateRating']['reviewCount'] ?? 0), 'image' => is_string($ld['image'] ?? null) ? $ld['image'] : null,
                'offers' => [], 'weight' => null, 'in_stock' => $price > 0,
            ];
        }
        $row['id'] = $id;
        $images = $row['images'] ?? array_values(array_filter([$row['image'] ?? null]));

        return $row + [
            'images' => $images,
            'prime' => false, 'best_seller' => false, 'amazons_choice' => false, 'sales_volume' => '',
            'deliverable' => ! preg_match('/not deliverable|does not deliver/i', $html),
            'stock_text' => ($row['in_stock'] ?? true) ? 'In stock' : 'Out of stock',
            'delivery_text' => null,
            'bullets' => array_values(array_filter([Text::clean((string) ($ld['description'] ?? ''))])),
            'specs' => array_filter(['Brand' => $row['brand'] ?? null, 'Weight' => $row['weight'] ?? null]),
            'variants' => [], 'videos' => [], 'has_videos' => false, 'aplus' => [], 'review_list' => [],
            'star_breakdown' => [], 'category_path' => [],
            'deal_ends_at' => null, 'coupon_inr' => null, 'effective_price_inr' => null,
        ];
    }

    public function listing(string $html): SearchPage
    {
        $state = (new HtmlDocument($html))->embeddedState();
        $rows = [];
        foreach (HtmlDocument::find($state, fn ($n) => isset($n['desc'], $n['pricing'], $n['id'])) as $n) {
            $row = $this->row($n);
            if ($row && $row['price_inr'] > 0) {
                $rows[$row['id']] = $row;
            }
        }

        return new SearchPage(array_values($rows), count($rows), 1);
    }

    /**
     * @param  array<string, mixed>  $n
     * @return array<string, mixed>|null
     */
    public function row(array $n): ?array
    {
        $disc = (array) ($n['pricing']['discount'] ?? []);
        $price = Text::money($disc['prim_price']['sp'] ?? $n['pricing']['sp'] ?? null) ?? 0.0;
        $mrp = Text::money($disc['mrp'] ?? $n['pricing']['mrp'] ?? null) ?? $price;
        $images = array_values(array_filter(array_map(fn ($i) => is_array($i) ? ($i['l'] ?? $i['m'] ?? $i['s'] ?? null) : $i, (array) ($n['images'] ?? []))));
        $offers = [];
        $dText = Text::clean((string) ($disc['d_text'] ?? ''));
        if ($dText !== '' && $mrp > $price) {
            $offers[] = Offers::make('deal', $dText, null, $mrp - $price);
        }
        foreach ([$disc['offer_entry_text_pos'] ?? null, $disc['offer_text'] ?? null, ...array_map(fn ($t) => is_array($t) ? ($t['header'] ?? $t['label'] ?? $t['value'] ?? null) : $t, (array) ($n['tags'] ?? []))] as $tag) {
            $tag = Text::clean(is_string($tag) ? $tag : '');
            if ($tag === '' || $tag === $dText) {
                continue;
            }
            $type = Offers::classify($tag);
            $offers[] = Offers::make($type === 'other' && preg_match('/off|save/i', $tag) ? 'other' : $type, $tag, null, Offers::rupees($tag), applies: false);
        }

        return [
            'id' => 'bb'.($n['id'] ?? ''),
            'market' => 'bigbasket',
            'title' => Text::clean(trim(((string) ($n['desc'] ?? '')).' '.((string) ($n['w'] ?? '')))),
            'brand' => Text::clean((string) (is_array($n['brand'] ?? null) ? ($n['brand']['name'] ?? '') : ($n['brand'] ?? ''))) ?: null,
            'price_inr' => $price,
            'mrp_inr' => max($mrp, $price),
            'rating' => (float) ($n['rating_info']['avg_rating'] ?? 0),
            'reviews' => (int) ($n['rating_info']['rating_count'] ?? 0),
            'image' => $images[0] ?? null,
            'images' => $images,
            'weight' => isset($n['w']) ? (string) $n['w'] : null,
            'in_stock' => ! in_array(strtoupper((string) ($n['availability']['avail_status'] ?? '001')), ['002', 'OOS'], true),
            'offers' => $offers,
            'deal_badge' => $dText !== '' ? $dText : null,
            'prime' => false, 'best_seller' => false, 'amazons_choice' => false, 'sales_volume' => '',
        ];
    }
}
