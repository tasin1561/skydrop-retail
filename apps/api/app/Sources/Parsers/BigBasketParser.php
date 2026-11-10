<?php

namespace App\Sources\Parsers;

use App\Sources\Support\Offers;
use App\Sources\Support\SearchPage;
use App\Sources\Support\Text;

/**
 * bigbasket.com pages (Next.js, __NEXT_DATA__). Live-checked Nov 2026:
 * - product pages (/pd/{id}/) carry props.pageProps.productDetails.children[]: the product ({id, desc, w, brand,
 *   pricing.discount.{mrp, prim_price.sp, d_text}, images, tags, breadcrumb, tabs, rating_info, availability}) with its
 *   other pack sizes nested in children[0].children[];
 * - the desktop search / category / offers pages no longer carry products (they load in the browser after a delivery
 *   location is chosen). The MOBILE site (user_agent_type mobile) still builds category (/pc/…, /cl/…) and brand (/pb/…)
 *   pages on the server, streamed as React Server Components (self.__next_f.push): listing() decodes those.
 * - the mobile product page only has the title, which carries the live price ("… Best Price of Rs 66 - bigbasket"):
 *   mobileTitle() reads it.
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
        $node = $items[0] ?? null;
        $row = $node ? $this->row($node) : null;
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
        $bullets = $node ? $this->about($node) : [];

        return $row + [
            'images' => $images,
            'prime' => false, 'best_seller' => false, 'amazons_choice' => false, 'sales_volume' => '',
            // Fetched without a delivery location; delivery to the Kolkata hub is confirmed when we buy.
            'deliverable' => true,
            'stock_text' => ($row['in_stock'] ?? true) ? 'In stock' : 'Out of stock',
            'delivery_text' => null,
            'bullets' => $bullets ?: array_values(array_filter([Text::clean((string) ($ld['description'] ?? ''))])),
            'specs' => array_filter(['Brand' => $row['brand'] ?? null, 'Pack size' => $row['weight'] ?? null, 'Food type' => $node ? $this->foodType($node) : null]),
            'variants' => $node ? $this->packs($state, $node, $id) : [],
            'videos' => [], 'has_videos' => false, 'aplus' => [], 'review_list' => [],
            'star_breakdown' => [],
            'category_path' => $node ? $this->breadcrumb($node) : [],
            'deal_ends_at' => null, 'coupon_inr' => null, 'effective_price_inr' => null,
        ];
    }

    public function listing(string $html): SearchPage
    {
        $state = (new HtmlDocument($html))->embeddedState() ?? self::rsc($html);
        $rows = [];
        foreach (HtmlDocument::find($state, fn ($n) => isset($n['desc'], $n['pricing'], $n['id']), 500) as $n) {
            $row = $this->row($n);
            if ($row && $row['price_inr'] > 1) { // a few listings carry a placeholder ₹1 price
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
        foreach ([$disc['offer_entry_text_pos'] ?? null, $disc['offer_entry_text'] ?? null, $disc['offer_text'] ?? null, ...array_map(fn ($t) => is_array($t) ? ($t['header'] ?? $t['label'] ?? $t['value'] ?? null) : $t, (array) ($n['tags'] ?? []))] as $tag) {
            $tag = Text::clean(is_string($tag) ? $tag : '');
            // Tags are mostly collections ("PRESERVATIVE FREE", "WORLD FOOD DAY", "DIWALI"): only real offers are kept.
            if ($tag === '' || $tag === $dText || ! self::isOffer($tag)) {
                continue;
            }
            $offers[] = Offers::make(Offers::classify($tag), $tag, null, Offers::rupees($tag), applies: false);
        }
        $brand = Text::clean((string) (is_array($n['brand'] ?? null) ? ($n['brand']['name'] ?? '') : ($n['brand'] ?? '')));
        $name = Text::clean(trim(((string) ($n['desc'] ?? '')).' '.((string) ($n['w'] ?? ''))));
        $rating = (float) ($n['rating_info']['avg_rating'] ?? 0);

        return [
            'id' => 'bb'.($n['id'] ?? ''),
            'market' => 'bigbasket',
            // BigBasket names leave the brand out ("Onion 1 kg" by fresho!): add it, as the store shows it.
            'title' => $brand !== '' && ! str_starts_with(mb_strtolower($name), mb_strtolower($brand)) ? "$brand $name" : $name,
            'brand' => $brand !== '' ? $brand : null,
            'price_inr' => $price,
            'mrp_inr' => max($mrp, $price),
            'rating' => round($rating, 1),
            'reviews' => (int) ($n['rating_info']['rating_count'] ?? 0),
            'image' => $images[0] ?? null,
            'images' => $images,
            'weight' => isset($n['w']) && $n['w'] !== '' ? (string) $n['w'] : null,
            'in_stock' => $price > 1 // a placeholder ₹1 price (seen on real listings) is not a price we can sell at
                && ! in_array(strtoupper((string) ($n['availability']['avail_status'] ?? '001')), ['002', 'OOS'], true)
                && ! ($n['availability']['not_for_sale'] ?? false),
            'offers' => $offers,
            'deal_badge' => $dText !== '' ? $dText : null,
            'prime' => false, 'best_seller' => false, 'amazons_choice' => false, 'sales_volume' => '',
        ];
    }

    /**
     * Decode a Next.js App Router page: the self.__next_f.push([1,"…"]) chunks joined are lines of "id:value" (JSON, or
     * "T<hex length>,<text>"), where "$<id>" strings point at other lines. Returns the lines as one array with the
     * references resolved (product objects end up complete: pricing, images, availability, brand…).
     *
     * @return array<string, mixed>|null
     */
    public static function rsc(string $html): ?array
    {
        // Split instead of one big regex (pages are 2-5 MB: a regex runs into PCRE's backtracking limit).
        $pieces = explode('self.__next_f.push([1,', $html);
        if (count($pieces) < 2) {
            return null;
        }
        $text = '';
        foreach (array_slice($pieces, 1) as $piece) {
            $end = strpos($piece, '])</script>');
            $chunk = json_decode($end === false ? $piece : substr($piece, 0, $end));
            $text .= is_string($chunk) ? $chunk : '';
        }
        $rows = [];
        $i = 0;
        $n = strlen($text);
        while ($i < $n) {
            if (! preg_match('/\G([0-9a-f]+):/', $text, $k, 0, $i)) {
                $j = strpos($text, "\n", $i);
                $i = $j === false ? $n : $j + 1;

                continue;
            }
            $i += strlen($k[0]);
            if (($text[$i] ?? '') === 'T' && ($c = strpos($text, ',', $i)) !== false) {
                $len = (int) hexdec(substr($text, $i + 1, $c - $i - 1));
                $rows[$k[1]] = substr($text, $c + 1, $len);
                $i = $c + 1 + $len;

                continue;
            }
            $j = strpos($text, "\n", $i);
            $j = $j === false ? $n : $j;
            $v = substr($text, $i, $j - $i);
            $i = $j + 1;
            if ($v !== '' && str_contains('{["', $v[0])) {
                $d = json_decode($v, true);
                if ($d !== null) {
                    $rows[$k[1]] = $d;
                }
            }
        }
        if ($rows === []) {
            return null;
        }
        $resolve = function ($v, int $depth = 0) use (&$resolve, $rows) {
            if ($depth > 25) {
                return $v;
            }
            if (is_string($v) && preg_match('/^\$([0-9a-f]+)$/', $v, $r) && array_key_exists($r[1], $rows)) {
                return $resolve($rows[$r[1]], $depth + 1);
            }
            if (is_array($v)) {
                foreach ($v as $key => $item) {
                    $v[$key] = $resolve($item, $depth + 1);
                }
            }

            return $v;
        };
        $out = [];
        foreach ($rows as $key => $v) {
            // Only product-like objects need resolving (resolving every line of a 2 MB page is slow).
            if (is_array($v) && isset($v['desc'], $v['pricing'], $v['id'])) {
                $out[$key] = $resolve($v);
            }
        }

        return $out;
    }

    /**
     * The mobile product page: title "Buy Fresho Onion 1 Kg Online At Best Price of Rs 66 - bigbasket".
     *
     * @return array{title: string, price: float}|null
     */
    public static function mobileTitle(string $html): ?array
    {
        if (! preg_match('#<title>\s*Buy (.+?) Online At Best Price of Rs\.? ?([\d,.]+)\s*-\s*bigbasket\s*</title>#i', $html, $m)) {
            return null;
        }
        $price = Text::money($m[2]);

        return $price ? ['title' => Text::clean(html_entity_decode($m[1])), 'price' => $price] : null;
    }

    public static function isOffer(string $tag): bool
    {
        return (bool) preg_match('/\d+\s*%\s*off|(₹|rs\.?)\s*\d+\s*off|\bsave\b|cashback|\bbuy\s*\d+\s*get\b|\bb\dg\d\b|\bcombo\b|\bfree\s+(gift|item|product)\b|\bget\s+\d+\s+free\b|\b\d+\s*\+\s*\d+\s*free\b|1\s*get\s*1|bogo/i', $tag)
            || (preg_match('/\bfree\b/i', $tag) && preg_match('/\bbuy\b|\bget\b/i', $tag));
    }

    /**
     * Other pack sizes of the same product (500 g, 2 kg, 5 kg…): each is its own BigBasket product.
     *
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private function packs(?array $state, array $node, string $id): array
    {
        $siblings = [];
        foreach ([$node, ...array_filter((array) ($node['children'] ?? []), 'is_array')] as $c) {
            if (isset($c['id'], $c['pricing'])) {
                $siblings['bb'.$c['id']] = $c;
            }
        }
        // Some pages list the packs side by side under productDetails.children instead.
        foreach (HtmlDocument::find($state, fn ($n) => isset($n['children']) && is_array($n['children']) && array_is_list($n['children'])
            && isset($n['children'][0]['desc'], $n['children'][0]['pricing']), 1) as $holder) {
            foreach ($holder['children'] as $c) {
                if (is_array($c) && isset($c['id'], $c['pricing'])) {
                    $siblings['bb'.$c['id']] ??= $c;
                }
            }
        }
        if (count($siblings) < 2) {
            return [];
        }
        $labels = [];
        $out = [];
        foreach ($siblings as $sid => $c) {
            $pack = Text::clean((string) ($c['w'] ?? '')) ?: Text::clean((string) ($c['pack_desc'] ?? '')) ?: 'Pack';
            $label = $pack;
            for ($k = 2; isset($labels[$label]); $k++) {
                $label = "$pack ($k)"; // two 2 kg packs (different offers) stay separate choices
            }
            $labels[$label] = true;
            $row = $this->row($c);
            $out[] = ['id' => $sid, 'label' => $label, 'dimensions' => ['Pack size' => $label],
                'swatch' => null, 'selected' => $sid === $id, 'in_stock' => (bool) ($row['in_stock'] ?? true)];
        }

        return $out;
    }

    /** @param array<string, mixed> $node @return list<string> */
    private function breadcrumb(array $node): array
    {
        $crumbs = array_values(array_filter(array_map(fn ($b) => is_array($b) ? ucwords(Text::clean((string) ($b['name'] ?? ''))) : '', (array) ($node['breadcrumb'] ?? []))));
        if ($crumbs) {
            return $crumbs;
        }
        $cat = (array) ($node['category'] ?? []);

        return array_values(array_filter([Text::clean((string) ($cat['tlc_name'] ?? '')), Text::clean((string) ($cat['llc_name'] ?? ''))]));
    }

    /** "About the Product", "Storage"… tabs as plain lines. @param array<string, mixed> $node @return list<string> */
    private function about(array $node): array
    {
        $out = [];
        foreach ((array) ($node['tabs'] ?? []) as $t) {
            $title = Text::clean((string) ($t['title'] ?? ''));
            if (! is_array($t) || preg_match('/other product info|disclaimer|seller/i', $title)) {
                continue;
            }
            $html = (string) preg_replace('#<style.*?</style>#is', '', (string) ($t['content'] ?? ''));
            $html = (string) preg_replace('/[\r\n]+/', ' ', $html); // hard-wrapped text: paragraphs end at tags only
            $html = (string) preg_replace('#<\s*(br|/p|/li|/div)\s*/?>#i', "\n", $html);
            foreach (preg_split('/\n+/', html_entity_decode(strip_tags($html))) ?: [] as $line) {
                $line = Text::clean($line);
                if (mb_strlen($line) > 3) {
                    $out[] = Text::truncate($line, 400);
                }
            }
        }

        return array_values(array_unique(array_slice($out, 0, 10)));
    }

    /** @param array<string, mixed> $node */
    private function foodType(array $node): ?string
    {
        foreach ((array) ($node['additional_attr']['info'] ?? []) as $i) {
            if (is_array($i) && ($i['type'] ?? '') === 'food_type' && ($i['label'] ?? '') !== '') {
                return ucfirst(Text::clean((string) $i['label']));
            }
        }

        return null;
    }
}
