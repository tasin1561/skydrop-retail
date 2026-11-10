<?php

namespace App\Sources\Parsers;

use App\Sources\Support\Offers;
use App\Sources\Support\SearchPage;
use App\Sources\Support\Text;

/**
 * Best-effort parser for flipkart.com pages fetched through Oxylabs (product pages rendered, flipkart_search).
 * Flipkart class names are obfuscated, so we lean on JSON-LD, the page state (window.__INITIAL_STATE__, "Atlas" widgets),
 * URLs (/p/itm...?pid=...) and visible text ("Available offers").
 *
 * Product ids: "itm…" is the product family; a specific variant (colour/storage) is its "pid". We store
 * "itm…-pid" (lower case) when the pid is known, so a search result or variant opens exactly that variant.
 * A bare "itm…" opens Flipkart's default variant.
 */
class FlipkartHtmlParser
{
    /** "itm…" or "itm…-pid" → [itm, PID|null] */
    public static function splitId(string $id): array
    {
        if (preg_match('/^(itm[a-z0-9]+)(?:-([a-z0-9]{8,20}))?$/i', trim($id), $m)) {
            return [strtolower($m[1]), isset($m[2]) && $m[2] !== '' ? strtoupper($m[2]) : null];
        }

        return [strtolower(trim($id)), null];
    }

    /** Our id for a flipkart.com product URL (keeps the variant pid when present). */
    public static function idFromUrl(string $url): ?string
    {
        if (! preg_match('#/p/(itm[a-z0-9]+)#i', $url, $m)) {
            return null;
        }
        $pid = preg_match('/[?&](?:amp;)?pid=([a-z0-9]{8,20})/i', $url, $p) ? strtolower($p[1]) : null;

        return strtolower($m[1]).($pid ? '-'.$pid : '');
    }

    /** Product page URL for an id. */
    public static function productUrl(string $id): string
    {
        [$itm, $pid] = self::splitId($id);

        return 'https://www.flipkart.com/-/p/'.$itm.($pid ? '?pid='.$pid : '');
    }

    /** @return array<string, mixed> */
    public function product(string $html, string $id): array
    {
        $doc = new HtmlDocument($html);
        $ld = $doc->jsonLdOfType('Product') ?? [];
        $offer = $ld['offers'] ?? [];
        $offer = is_array($offer) && array_is_list($offer) ? ($offer[0] ?? []) : (array) $offer;
        $ppd = $this->ppd($html);
        $state = $this->state($html);

        $price = Text::money($ppd['finalPrice'] ?? $ppd['fsp'] ?? null) ?? Text::money($offer['price'] ?? null) ?? $this->firstRupee($doc) ?? 0.0;
        $mrp = Text::money($ppd['mrp'] ?? null) ?? $this->mrp($html, $price);
        $images = array_values(array_filter(array_map(fn ($u) => is_string($u) ? $u : ($u['url'] ?? null), (array) ($ld['image'] ?? []))));
        $stock = (string) ($offer['availability'] ?? '');
        $inStock = $price > 0 && ! str_contains(strtolower($stock), 'outofstock') && ! preg_match('/sold out|currently unavailable/i', $doc->text('//body'));
        $offers = $this->availableOffers($doc, $price);
        $special = collect($offers)->firstWhere('type', 'special_price');
        $currentPid = strtoupper((string) ($ld['sku'] ?? '')) ?: (self::splitId($id)[1] ?? '');
        $bullets = $this->highlights($doc) ?: $this->stateDescriptions($state);
        $specs = $this->stateSpecs($state) ?: $this->specs($doc);

        return [
            'id' => $id,
            'market' => 'flipkart',
            'title' => Text::clean((string) ($ld['name'] ?? $doc->text('//h1'))),
            'brand' => Text::clean(is_array($ld['brand'] ?? null) ? (string) ($ld['brand']['name'] ?? '') : (string) ($ld['brand'] ?? '')),
            'price_inr' => $price,
            'mrp_inr' => max($mrp, $price),
            'rating' => (float) ($ld['aggregateRating']['ratingValue'] ?? 0),
            'reviews' => (int) ($ld['aggregateRating']['ratingCount'] ?? $ld['aggregateRating']['reviewCount'] ?? 0),
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
            'bullets' => $bullets,
            'specs' => $specs,
            'weight' => null,
            'variants' => $this->variants($state, $currentPid),
            'videos' => [],
            'has_videos' => false,
            'aplus' => [],
            'review_list' => [],
            'star_breakdown' => [],
            'category_path' => [],
            'offers' => $offers,
            'deal_badge' => $special || ($ppd['isSpecialPrice'] ?? false) ? 'Special Price' : null,
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
            $id = self::idFromUrl($a->getAttribute('href'));
            if ($id === null || isset($rows[$id])) {
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
            $rows[$id] = [
                'id' => $id,
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

    /** The main product's price block: {"fsp","finalPrice","mrp","specialPrice",…}. @return array<string, mixed> */
    private function ppd(string $html): array
    {
        if (preg_match('/"ppd"\s*:\s*(\{[^{}]*\})/', $html, $m)) {
            $j = json_decode($m[1], true);

            return is_array($j) ? $j : [];
        }

        return [];
    }

    /** window.__INITIAL_STATE__ (the new "Atlas" product page). @return array<string, mixed> */
    private function state(string $html): array
    {
        if (! preg_match('/window\.__INITIAL_STATE__\s*=\s*(\{.*?\})\s*;\s*<\/script>/s', $html, $m)) {
            return [];
        }
        $s = json_decode($m[1], true);

        return is_array($s) ? $s : [];
    }

    /**
     * Colour / storage selectors → one entry per option, in the same shape as Amazon variants.
     * Each option links to its own pid; options keep the current values of the other selectors
     * (that is how Flipkart switches), and the opened page shows the exact variant.
     *
     * @return list<array<string, mixed>>
     */
    private function variants(array $state, string $currentPid): array
    {
        $groups = [];
        $this->walk($state, function (string $key, mixed $node) use (&$groups) {
            if (! str_starts_with($key, 'variant-selector-') || ! is_array($node)) {
                return;
            }
            $v = (array) ($node['value'] ?? $node);
            $header = (array) ($v['selector-header_0']['value'] ?? []);
            $name = trim(preg_replace('/^Selected\s+/i', '', rtrim(self::text($header['label_0'] ?? null), ': ')) ?? '');
            $list = $v['horizontalListData_0']['value'] ?? $v['scrollToListData_0']['value'] ?? [];
            $options = [];
            foreach ((array) $list as $row) {
                $item = (array) ($row['value'] ?? $row);
                $url = null;
                $title = null;
                $image = null;
                $texts = [];
                $this->walk($item, function (string $k, mixed $n) use (&$url, &$title, &$image, &$texts) {
                    if ($k === 'url' && is_string($n) && $url === null && str_contains($n, '/p/itm')) {
                        $url = $n;
                    } elseif ($k === 'contentTitle' && is_string($n) && $title === null) {
                        $title = $n;
                    } elseif ($k === 'dynamicImageUrl' && is_string($n) && $image === null) {
                        $image = $n;
                    } elseif ($k === 'text') {
                        $texts[] = self::text(['value' => ['text' => $n]]);
                    }
                });
                $id = $url ? self::idFromUrl($url) : null;
                if (! $id) {
                    continue;
                }
                $label = trim(self::text($item['label_0'] ?? null)) ?: trim((string) $title);
                if ($label === '') {
                    continue;
                }
                $options[] = [
                    'id' => $id,
                    'value' => Text::clean($label),
                    'oos' => in_array('out of stock', array_map(fn ($t) => strtolower(trim($t)), $texts), true),
                    'swatch' => $image ? str_replace(['{@width}', '{@height}', '{@quality}'], ['128', '128', '70'], $image) : null,
                ];
            }
            if ($options) {
                $groups[] = ['name' => $name !== '' ? Text::label($name) : 'Option '.(count($groups) + 1), 'current' => trim(self::text($header['label_1'] ?? null)), 'options' => $options];
            }
        });
        if (! $groups) {
            return [];
        }

        $currentId = null;
        foreach ($groups as $g) {
            foreach ($g['options'] as $o) {
                if ($currentPid !== '' && strtoupper((string) (self::splitId($o['id'])[1] ?? '')) === $currentPid) {
                    $currentId = $o['id'];
                }
            }
        }
        // The selected value per selector: the header text, else the option pointing at this page.
        $current = [];
        foreach ($groups as $g) {
            $sel = $g['current'];
            if ($sel === '') {
                foreach ($g['options'] as $o) {
                    if ($o['id'] === $currentId) {
                        $sel = $o['value'];
                    }
                }
            }
            if ($sel !== '') {
                $current[$g['name']] = $sel;
            }
        }

        $out = [];
        foreach ($groups as $g) {
            foreach ($g['options'] as $o) {
                if (isset($out[$o['id']])) {
                    continue;
                }
                $dims = [$g['name'] => $o['value']] + $current;
                $dims[$g['name']] = $o['value'];
                $ordered = [];
                foreach ($groups as $gg) {
                    if (isset($dims[$gg['name']])) {
                        $ordered[$gg['name']] = $dims[$gg['name']];
                    }
                }
                $out[$o['id']] = [
                    'id' => $o['id'],
                    'label' => implode(' / ', $ordered),
                    'dimensions' => $ordered,
                    'swatch' => $o['swatch'],
                    'selected' => $o['id'] === $currentId,
                    'in_stock' => ! $o['oos'],
                ];
            }
        }

        return array_values($out);
    }

    /** Specification grid (label_0 = name, label_1 = value) under the "rpd_specifications…" widgets. @return array<string, string> */
    private function stateSpecs(array $state): array
    {
        $specs = [];
        $this->walk($state, function (string $key, mixed $node) use (&$specs) {
            if (! str_starts_with($key, 'rpd_specifications') || ! is_array($node)) {
                return;
            }
            $this->walk($node, function (string $k, mixed $n) use (&$specs) {
                if (! is_array($n) || ! isset($n['label_0'], $n['label_1']) || isset($n['rpd_grid_0'])) {
                    return;
                }
                $name = Text::label(self::text($n['label_0']));
                $value = preg_replace('/^\?+(?=\S)/', '', Text::clean(self::text($n['label_1']))) ?? ''; // Flipkart sometimes ships a broken leading "?"
                if ($name !== '' && $value !== '' && ! isset($specs[$name]) && count($specs) < 80) {
                    $specs[$name] = Text::truncate($value, 300);
                }
            });
        });

        return $specs;
    }

    /** "Description" paragraphs of the new layout, used as bullets when the page has no Highlights list. @return list<string> */
    private function stateDescriptions(array $state): array
    {
        $out = [];
        $this->walk($state, function (string $key, mixed $node) use (&$out) {
            if (str_starts_with($key, 'rpd_description_item') && is_array($node) && count($out) < 8) {
                $t = Text::clean(self::text(($node['value'] ?? $node)['label_0'] ?? null));
                if ($t !== '' && ! in_array($t, $out, true)) {
                    $out[] = Text::truncate($t, 300);
                }
            }
        });

        return $out;
    }

    /** Text of an Atlas label: {"value": {"text": "…" | ["…"]}}. */
    private static function text(mixed $label): string
    {
        $t = is_array($label) ? ($label['value']['text'] ?? $label['text'] ?? '') : $label;
        if (is_array($t)) {
            $t = implode(', ', array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $t)));
        }

        return is_scalar($t) ? (string) $t : '';
    }

    /** Depth-first walk calling $fn(key, node) for every keyed node. */
    private function walk(mixed $node, callable $fn, int $depth = 0): void
    {
        if (! is_array($node) || $depth > 60) {
            return;
        }
        foreach ($node as $k => $v) {
            if (is_string($k)) {
                $fn($k, $v);
            }
            if (is_array($v)) {
                $this->walk($v, $fn, $depth + 1);
            }
        }
    }

    private function mrp(string $html, float $price): float
    {
        // A plain number first: '"mrp":{"value":…}' also appears for add-ons (warranty plans), not the product.
        if (preg_match('/"mrp"\s*:\s*"?(\d+(?:\.\d+)?)/', $html, $m)
            || preg_match('/"mrp"\s*:\s*\{[^{}]*?"(?:value|decimalValue)"\s*:\s*"?(\d+(?:\.\d+)?)/', $html, $m)) {
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
