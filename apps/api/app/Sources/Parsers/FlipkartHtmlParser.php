<?php

namespace App\Sources\Parsers;

use App\Sources\Support\Offers;
use App\Sources\Support\SearchPage;
use App\Sources\Support\Text;

/**
 * Best-effort parser for flipkart.com pages fetched through Oxylabs (flipkart_product rendered, flipkart_search).
 * Flipkart class names are obfuscated, so we lean on JSON-LD, URLs (/p/itm...) and visible text ("Available offers").
 * Product ids are the "itm..." part of the product URL.
 */
class FlipkartHtmlParser
{
    /** @return array<string, mixed> */
    public function product(string $html, string $id): array
    {
        $doc = new HtmlDocument($html);
        $ld = $doc->jsonLdOfType('Product') ?? [];
        $offer = $ld['offers'] ?? [];
        $offer = is_array($offer) && array_is_list($offer) ? ($offer[0] ?? []) : (array) $offer;

        $price = Text::money($offer['price'] ?? null) ?? $this->firstRupee($doc) ?? 0.0;
        $mrp = $this->mrp($html, $price);
        $images = array_values(array_filter(array_map(fn ($u) => is_string($u) ? $u : ($u['url'] ?? null), (array) ($ld['image'] ?? []))));
        $stock = (string) ($offer['availability'] ?? '');
        $inStock = $price > 0 && ! str_contains(strtolower($stock), 'outofstock') && ! preg_match('/sold out|currently unavailable/i', $doc->text('//body'));
        $offers = $this->availableOffers($doc, $price);
        $special = collect($offers)->firstWhere('type', 'special_price');

        return [
            'id' => $id,
            'market' => 'flipkart',
            'title' => Text::clean((string) ($ld['name'] ?? $doc->text('//h1'))),
            'brand' => Text::clean(is_array($ld['brand'] ?? null) ? (string) ($ld['brand']['name'] ?? '') : (string) ($ld['brand'] ?? '')),
            'price_inr' => $price,
            'mrp_inr' => max($mrp, $price),
            'rating' => (float) ($ld['aggregateRating']['ratingValue'] ?? 0),
            'reviews' => (int) ($ld['aggregateRating']['reviewCount'] ?? $ld['aggregateRating']['ratingCount'] ?? 0),
            'image' => $images[0] ?? null,
            'images' => $images,
            'prime' => false,
            'best_seller' => false,
            'amazons_choice' => false,
            'sales_volume' => '',
            'in_stock' => $inStock,
            'deliverable' => ! preg_match('/not deliverable|does not deliver|cannot be delivered/i', $html),
            'stock_text' => $inStock ? 'In stock' : 'Currently unavailable',
            'delivery_text' => null,
            'bullets' => $this->highlights($doc),
            'specs' => $this->specs($doc),
            'weight' => null,
            'variants' => [],
            'videos' => [],
            'has_videos' => false,
            'aplus' => [],
            'review_list' => [],
            'star_breakdown' => [],
            'category_path' => [],
            'offers' => $offers,
            'deal_badge' => $special ? 'Special Price' : null,
            'deal_ends_at' => null,
            'coupon_inr' => null,
            'effective_price_inr' => null,
        ];
    }

    /** Listing cards from a search or offers-store page. */
    public function listing(string $html): SearchPage
    {
        $doc = new HtmlDocument($html);
        $rows = [];
        foreach ($doc->x->query('//a[contains(@href,"/p/itm")]') ?: [] as $a) {
            /** @var \DOMElement $a */
            if (! preg_match('#/p/(itm[a-z0-9]+)#i', $a->getAttribute('href'), $m) || isset($rows[$m[1]])) {
                continue;
            }
            // Walk up to the card (the closest ancestor carrying a price).
            $card = $a;
            for ($i = 0; $i < 6 && $card->parentNode instanceof \DOMElement && ! str_contains($card->textContent, '₹'); $i++) {
                $card = $card->parentNode;
            }
            $text = implode(' ', array_map(fn ($n) => trim($n->textContent), iterator_to_array($doc->x->query('.//text()', $card) ?: [])));
            preg_match_all('/₹\s*([\d,]+)/u', $text, $pm);
            $prices = array_map(fn ($p) => (float) str_replace(',', '', $p), $pm[1]);
            $title = $a->getAttribute('title') ?: $doc->text('.//img/@alt', $a) ?: Text::clean($a->textContent);
            if (! $prices || $title === '') {
                continue;
            }
            $img = $doc->x->query('.//img', $card)?->item(0);
            $rating = preg_match('/(?<![\d,.])([1-5]\.\d)(?![\d,])/', $text, $rm) ? (float) $rm[1] : 0.0;
            $rows[$m[1]] = [
                'id' => strtolower($m[1]),
                'market' => 'flipkart',
                'title' => Text::clean($title),
                'brand' => null,
                'price_inr' => $prices[0],
                'mrp_inr' => max($prices[1] ?? $prices[0], $prices[0]),
                'rating' => $rating,
                'reviews' => preg_match('/([\d,]+)\s*Ratings/i', $text, $cm) ? Text::count($cm[1]) : 0,
                'image' => $img instanceof \DOMElement ? ($img->getAttribute('src') ?: null) : null,
                'prime' => false, 'best_seller' => false, 'amazons_choice' => false, 'sales_volume' => '',
                'offers' => [],
            ];
        }

        return new SearchPage(array_values($rows), count($rows), 1);
    }

    /** @return list<array<string, mixed>> */
    public function availableOffers(HtmlDocument $doc, float $price): array
    {
        $heading = $doc->x->query('//*[normalize-space(text())="Available offers" or normalize-space(text())="Available Offers"]')?->item(0);
        if (! $heading) {
            return [];
        }
        $box = $heading->parentNode;
        for ($i = 0; $i < 3 && $box && ($doc->x->query('.//li', $box)?->length ?? 0) === 0; $i++) {
            $box = $box->parentNode;
        }
        $out = [];
        foreach ($box ? $doc->x->query('.//li', $box) : [] as $li) {
            $text = Text::clean($li->textContent);
            $text = preg_replace('/\s*T&C\s*$/', '', $text) ?? $text;
            if ($text === '') {
                continue;
            }
            $label = preg_match('/^(Bank Offer|Special Price|Partner Offer|Combo Offer|No Cost EMI|Exchange Offer|Freebie|Cashback)/i', $text, $m) ? $m[1] : 'Offer';
            $detail = trim(mb_substr($text, mb_strlen($label)));
            $type = Offers::classify($label);
            $saving = Offers::rupees($detail) ?? (preg_match('/(\d+)%\s*off/i', $detail, $pm) && $price > 0 ? round($price * (int) $pm[1] / 100, 2) : null);
            $out[] = Offers::make($type, $label, $detail, $saving);
        }

        return $out;
    }

    private function mrp(string $html, float $price): float
    {
        if (preg_match('/"mrp"\s*:\s*\{?[^{}]*?"(?:value|decimalValue)"\s*:\s*"?(\d+(?:\.\d+)?)/', $html, $m)
            || preg_match('/"mrp"\s*:\s*"?(\d+(?:\.\d+)?)/', $html, $m)) {
            return (float) $m[1];
        }

        return $price;
    }

    private function firstRupee(HtmlDocument $doc): ?float
    {
        $h1 = $doc->x->query('//h1')?->item(0);
        $scope = $h1?->parentNode?->parentNode;

        return $scope && preg_match('/₹\s*([\d,]+)/u', $scope->textContent, $m) ? (float) str_replace(',', '', $m[1]) : null;
    }

    /** @return list<string> */
    private function highlights(HtmlDocument $doc): array
    {
        $out = [];
        $h = $doc->x->query('//*[normalize-space(text())="Highlights"]')?->item(0);
        foreach ($h?->parentNode ? $doc->x->query('.//li', $h->parentNode) : [] as $li) {
            $t = Text::clean($li->textContent);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private function specs(HtmlDocument $doc): array
    {
        $specs = [];
        foreach ($doc->x->query('//table//tr[count(td)=2]') ?: [] as $tr) {
            $k = Text::label($doc->text('./td[1]', $tr));
            $v = $doc->text('./td[2]', $tr);
            if ($k !== '' && $v !== '' && ! isset($specs[$k]) && count($specs) < 80) {
                $specs[$k] = Text::truncate($v, 300);
            }
        }

        return $specs;
    }
}
