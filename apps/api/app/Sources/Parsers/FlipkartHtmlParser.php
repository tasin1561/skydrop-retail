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
        [$gallery, $videos] = $this->media($state);
        $images = self::uniqueImages([
            ...array_filter(array_map(fn ($u) => is_string($u) ? $u : ($u['url'] ?? null), (array) ($ld['image'] ?? []))),
            ...$gallery,
        ]);
        // Stock: the product's own schema.org offer when present (page text also mentions other variants' stock).
        $stock = strtolower((string) ($offer['availability'] ?? ''));
        $inStock = $price > 0 && ($stock !== ''
            ? ! str_contains($stock, 'outofstock') && ! str_contains($stock, 'soldout') && ! str_contains($stock, 'discontinued')
            : ! preg_match('/sold out|currently unavailable/i', $doc->text('//h1/ancestor::*[3]')));
        $offers = [...$this->availableOffers($doc, $price), ...$this->stateOffers($state)];
        $special = collect($offers)->firstWhere('type', 'special_price');
        $currentPid = strtoupper((string) ($ld['sku'] ?? '')) ?: (self::splitId($id)[1] ?? '');
        $bullets = $this->highlights($doc) ?: $this->stateDescriptions($state);
        $specs = $this->stateSpecs($state) ?: $this->specs($doc);
        $reviews = $this->stateReviews($state) ?: $this->ldReviews($ld);

        return [
            'id' => $id,
            'market' => 'flipkart',
            'title' => Text::clean((string) ($ld['name'] ?? $doc->text('//h1'))),
            'brand' => self::brandCase(Text::clean(is_array($ld['brand'] ?? null) ? (string) ($ld['brand']['name'] ?? '') : (string) ($ld['brand'] ?? '')), (string) ($ld['name'] ?? '')),
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
            // Fetched without a delivery location, so every page says "Not deliverable at your location".
            // Delivery to the Kolkata hub is confirmed when we buy, as with any store without pincode support.
            'deliverable' => true,
            'stock_text' => $inStock ? 'In stock' : 'Currently unavailable',
            'delivery_text' => null,
            'bullets' => $bullets,
            'specs' => $specs,
            'weight' => $this->weight($specs), // shipping is charged by weight
            'variants' => $this->variants($state, $currentPid),
            'videos' => $videos,
            'has_videos' => $videos !== [],
            'aplus' => [],
            'review_list' => $reviews,
            'star_breakdown' => [],
            'category_path' => $this->breadcrumbs($state),
            'offers' => $offers,
            'deal_badge' => $special || ($ppd['isSpecialPrice'] ?? false) ? 'Special Price' : null,
            'deal_ends_at' => null,
            'coupon_inr' => null,
            'effective_price_inr' => null,
        ];
    }

    /**
     * Listing cards from a search or category (/pr?sid=…) page. Every product card carries data-id="<pid>";
     * list (phones, laptops), grid (fashion) and book layouts differ only in which parts are present.
     */
    public function listing(string $html): SearchPage
    {
        $doc = new HtmlDocument($html);
        $rows = [];
        foreach ($doc->x->query('//*[@data-id][.//a[contains(@href,"/p/itm")]][not(.//*[@data-id])]') ?: [] as $card) {
            /** @var \DOMElement $card */
            $link = $doc->x->query('.//a[contains(@href,"/p/itm")]', $card)?->item(0);
            $id = $link instanceof \DOMElement ? self::idFromUrl($link->getAttribute('href')) : null;
            if ($id === null || isset($rows[$id])) {
                continue;
            }
            $row = $this->card($doc, $card, $id);
            if ($row !== null) {
                $rows[$id] = $row;
            }
        }
        if (! $rows) {
            $rows = $this->linkCards($doc); // older markup without data-id cards
        }

        return new SearchPage(array_values($rows), count($rows), 1);
    }

    /** @return array<string, mixed>|null */
    private function card(HtmlDocument $doc, \DOMElement $card, string $id): ?array
    {
        $parts = [];
        foreach ($doc->x->query('.//text()[not(ancestor::*[local-name()="svg" or local-name()="script" or local-name()="style"])]', $card) ?: [] as $t) {
            $v = trim($t->textContent);
            if ($v !== '') {
                $parts[] = $v;
            }
        }
        $text = implode(' ', $parts);
        $prices = self::rupeeAmounts($text);
        if (! $prices) {
            return null;
        }
        $price = $prices[0];

        $titleLink = $doc->x->query('.//a[@title][contains(@href,"/p/itm")]', $card)?->item(0);
        $title = $titleLink instanceof \DOMElement ? $titleLink->getAttribute('title') : '';
        $brand = null;
        if ($titleLink instanceof \DOMElement) {
            $prev = $titleLink->previousSibling;
            while ($prev && ! $prev instanceof \DOMElement) {
                $prev = $prev->previousSibling;
            }
            $b = $prev ? Text::clean($prev->textContent) : '';
            if ($b !== '' && mb_strlen($b) <= 40 && ! str_contains($b, '₹') && ! preg_match('/^\d/', $b)) {
                $brand = $b; // grid cards show the brand above the title
            }
        }
        if ($title === '') {
            $img = $doc->x->query('.//img[@alt!=""]', $card)?->item(0);
            $title = $img instanceof \DOMElement ? $img->getAttribute('alt') : '';
        }
        if ($title === '') {
            // List layout: the longest text that is not a price, rating or spec line.
            $title = (string) collect($parts)->reject(fn ($p) => str_contains($p, '₹') || mb_strlen($p) < 12)->sortByDesc(fn ($p) => mb_strlen($p))->first();
        }
        $title = Text::clean(preg_replace('/\.{3}$|…$/u', '', $title) ?? $title);
        if ($title === '') {
            return null;
        }
        if ($brand && stripos($title, $brand) !== 0) {
            $title = $brand.' '.$title;
        }

        // MRP: the struck price; when "% off" is shown, the amount that matches that discount (ignores "Upto ₹4,150 off on exchange").
        $mrp = $price;
        $later = array_values(array_filter(array_slice($prices, 1), fn ($p) => $p > $price));
        if (preg_match('/(\d{1,2})%\s*off/i', $text, $pm) && $later) {
            $expect = $price / (1 - (int) $pm[1] / 100);
            usort($later, fn ($a, $b) => abs($a - $expect) <=> abs($b - $expect));
            $mrp = abs($later[0] - $expect) / $expect < 0.05 ? $later[0] : $mrp;
        } elseif ($later && ! preg_match('/exchange|upto|bank offer/i', $text)) {
            $mrp = $later[0]; // a struck price without a "% off" label
        }

        $rating = 0.0;
        foreach ($parts as $p) {
            if (preg_match('/^([1-5](?:\.\d)?)$/', $p, $rm)) {
                $rating = (float) $rm[1];
                break;
            }
        }
        $reviews = preg_match('/([\d,]+)\s*Ratings/i', $text, $cm) ? Text::count($cm[1])
            : (preg_match('/(?:^|\s)\(([\d,]+)\)(?:\s|$)/', $text, $cm) ? Text::count($cm[1]) : 0);

        $image = null;
        foreach ($doc->x->query('.//img', $card) ?: [] as $img) {
            /** @var \DOMElement $img */
            foreach ([$img->getAttribute('src'), $img->getAttribute('data-src'), explode(' ', trim($img->getAttribute('srcset')))[0] ?? ''] as $u) {
                if ($u !== '' && preg_match('#flixcart\.com/image/#', $u)) {
                    $image = $u;
                    break 2;
                }
            }
        }

        return [
            'id' => $id,
            'market' => 'flipkart',
            'title' => $title,
            'brand' => $brand,
            'price_inr' => $price,
            'mrp_inr' => max($mrp, $price),
            'rating' => $rating,
            'reviews' => $reviews,
            'image' => $image,
            'prime' => false, 'best_seller' => false, 'amazons_choice' => false, 'sales_volume' => '',
            'offers' => [],
        ];
    }

    /** Older markup: walk up from each product link to the closest block with a price. @return array<string, array<string, mixed>> */
    private function linkCards(HtmlDocument $doc): array
    {
        $rows = [];
        foreach ($doc->x->query('//a[contains(@href,"/p/itm")]') ?: [] as $a) {
            /** @var \DOMElement $a */
            $id = self::idFromUrl($a->getAttribute('href'));
            if ($id === null || isset($rows[$id])) {
                continue;
            }
            $card = $a;
            for ($i = 0; $i < 6 && $card->parentNode instanceof \DOMElement && ! str_contains($card->textContent, '₹'); $i++) {
                $card = $card->parentNode;
            }
            if ($card instanceof \DOMElement && ($row = $this->card($doc, $card, $id))) {
                $rows[$id] = $row;
            }
        }

        return $rows;
    }

    /** "₹5,199 ₹ 12,999" → [5199, 12999] @return list<float> */
    private static function rupeeAmounts(string $text): array
    {
        preg_match_all('/₹\s*([\d,]+(?:\.\d+)?)/u', $text, $pm);

        return array_values(array_filter(array_map(fn ($p) => (float) str_replace(',', '', $p), $pm[1]), fn ($v) => $v > 0));
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
            if (! is_array($node) || ! (str_starts_with($key, 'variant-selector-') || str_starts_with($key, 'size-selector-'))) {
                return;
            }
            $v = (array) ($node['value'] ?? $node);
            $header = (array) ($v['selector-header_0']['value'] ?? []);
            $name = trim(preg_replace('/^(?:Selected|Select|Choose)\s+(?:a\s+|your\s+)?/i', '', rtrim(self::text($header['label_0'] ?? null), ': ')) ?? '');
            $list = $v['horizontalListData_0']['value'] ?? $v['scrollToListData_0']['value'] ?? [];
            $options = [];
            foreach ((array) $list as $row) {
                $item = (array) ($row['value'] ?? $row);
                $url = null;
                $title = null;
                $image = null;
                $texts = [];
                $oos = false;
                $this->walk($item, function (string $k, mixed $n) use (&$url, &$title, &$image, &$texts, &$oos) {
                    if ($k === 'contentType' && is_string($n) && str_starts_with(strtolower($n), 'outofstock')) {
                        $oos = true;
                    }
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
                    'oos' => $oos || in_array('out of stock', array_map(fn ($t) => strtolower(trim($t)), $texts), true),
                    'swatch' => $image ? str_replace(['{@width}', '{@height}', '{@quality}'], ['128', '128', '70'], $image) : null,
                ];
            }
            if ($name === '' || preg_match('/choose|view your/i', $name)) {
                $name = str_starts_with($key, 'size-selector-') ? 'Size' : '';
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

    /**
     * Gallery images and videos from the media carousel ("multiMediaViewData").
     *
     * @return array{0: list<string>, 1: list<array{hls: string, thumb: ?string}>}
     */
    private function media(array $state): array
    {
        $images = [];
        $videos = [];
        $this->walk($state, function (string $key, mixed $node) use (&$images, &$videos) {
            if ($key !== 'multiMediaViewData_0' || ! is_array($node)) {
                return;
            }
            foreach ((array) ($node['value'] ?? []) as $item) {
                $item = (array) ($item['value'] ?? $item);
                $video = null;
                $thumb = null;
                $this->walk($item['video_0'] ?? [], function (string $k, mixed $n) use (&$video, &$thumb) {
                    if ($k === 'videoUrl' && is_string($n) && str_contains($n, '.m3u8')) {
                        $video ??= $n;
                    } elseif ($k === 'dynamicImageUrl' && is_string($n)) {
                        $thumb ??= self::sized($n, 640);
                    }
                });
                if ($video) {
                    $videos[$video] = ['hls' => $video, 'thumb' => $thumb];
                }
                $this->walk($item['image_0'] ?? [], function (string $k, mixed $n) use (&$images) {
                    if ($k === 'dynamicImageUrl' && is_string($n) && str_contains($n, '/image/')) {
                        $images[] = self::sized($n, 1500);
                    }
                });
            }
        });

        return [self::uniqueImages($images), array_values(array_slice($videos, 0, 6))];
    }

    /** Item weight from the specs ("Weight (without stand)": "7.4 kg"; books list grams without a unit). */
    private function weight(array $specs): ?string
    {
        foreach ($specs as $k => $v) {
            if (! preg_match('/weight/i', (string) $k) || preg_match('/capacity|load|max/i', (string) $k)) {
                continue;
            }
            if (preg_match('/(\d+(?:\.\d+)?)\s*(kg|kilograms?|g|grams?|gm)\b/i', (string) $v, $m)) {
                return $m[1].' '.(preg_match('/^k/i', $m[2]) ? 'kg' : 'g');
            }
            if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*$/', (string) $v, $m)) {
                return $m[1].((float) $m[1] >= 20 ? ' g' : ' kg'); // unitless: Flipkart uses grams (books) or kg
            }
        }
        foreach ($specs as $k => $v) {
            if (preg_match('/net quantity/i', (string) $k) && preg_match('/(\d+(?:\.\d+)?)\s*(kg|g)\b/i', (string) $v, $m)) {
                return $m[1].' '.strtolower($m[2]);
            }
        }

        return null;
    }

    /** "asics" → "Asics" when the title spells it that way; otherwise the brand as given. */
    private static function brandCase(string $brand, string $title): string
    {
        if ($brand !== '' && stripos($title, $brand) === 0) {
            return mb_substr(Text::clean($title), 0, mb_strlen($brand));
        }

        return $brand !== '' && $brand === mb_strtolower($brand) ? mb_convert_case($brand, MB_CASE_TITLE) : $brand;
    }

    /** Flipkart image templates: {@width}/{@height}/{@quality}. */
    private static function sized(string $url, int $px): string
    {
        return str_replace(['{@width}', '{@height}', '{@quality}'], [(string) $px, (string) $px, '70'], $url);
    }

    /** Same picture at different sizes or hosts counts once (keyed by the file name). @param list<string> $urls @return list<string> */
    private static function uniqueImages(array $urls): array
    {
        $out = [];
        foreach ($urls as $u) {
            if (! is_string($u) || $u === '' || str_contains($u, '{@')) {
                continue;
            }
            $key = strtolower(basename((string) parse_url($u, PHP_URL_PATH)));
            $out[$key] ??= $u;
        }

        return array_values(array_slice($out, 0, 20));
    }

    /**
     * Offers from the "Apply offers for maximum savings" block: bank cards, Flipkart coupons, exchange, No Cost EMI.
     * Bank/EMI/exchange need the buyer's own card or old item, and "#OnlyForYou" coupons belong to one Flipkart
     * account, so none of them lower the price we pay (shown as "on Flipkart" only).
     *
     * @return list<array<string, mixed>>
     */
    private function stateOffers(array $state): array
    {
        $out = [];
        $seen = [];
        $this->walk($state, function (string $key, mixed $node) use (&$out, &$seen) {
            if ($key !== 'offerSummariesRC' || ! is_array($node)) {
                return;
            }
            foreach ($node as $summary) {
                $v = (array) ($summary['value'] ?? $summary);
                $type = strtoupper((string) ($v['offerType'] ?? ''));
                $amount = Text::clean((string) ($v['discountedPriceText'] ?? ''));
                $sub = Text::clean(self::contentList($v['offerSubTitleRC'] ?? null));
                if ($type === 'PBO' && ! isset($seen['bank'])) {
                    $seen['bank'] = true;
                    // Group payment options with the same saving ("Google Pay, PhonePe and 9 more: ₹182 off").
                    $byAmount = [];
                    foreach ((array) ($v['bankOfferGrid'] ?? []) as $b) {
                        $b = (array) ($b['value'] ?? $b);
                        $bank = Text::clean((string) ($b['offerTitle'] ?? ''));
                        $off = Text::clean((string) ($b['discountedPriceText'] ?? ''));
                        if ($bank !== '' && $off !== '') {
                            $byAmount[$off][$bank] = true;
                        }
                    }
                    uksort($byAmount, fn ($a, $b) => (Offers::rupees($b) ?? 0) <=> (Offers::rupees($a) ?? 0));
                    foreach (array_slice($byAmount, 0, 6, true) as $off => $banks) {
                        $names = array_keys($banks);
                        $who = count($names) > 3 ? implode(', ', array_slice($names, 0, 2)).' and '.(count($names) - 2).' more' : implode(', ', $names);
                        $out[] = Offers::make('bank', 'Bank Offer', "$who: $off", Offers::rupees((string) $off));
                    }
                } elseif ($type === 'USER_COUPON_SELECTION' && ! isset($seen['coupon'])) {
                    $seen['coupon'] = true;
                    $out[] = Offers::make('coupon', 'Flipkart coupon', trim(($sub ?: 'Coupon').': '.$amount.' (for the Flipkart account it is shown to)'),
                        Offers::rupees($amount), applies: false);
                } elseif ($type === 'EXCHANGE' && ! isset($seen['exchange'])) {
                    $seen['exchange'] = true;
                    $out[] = Offers::make('exchange', 'Exchange offer', "$amount off on exchange", Offers::rupees($amount));
                }
            }
        });
        // No Cost EMI is stated on the EMI tab subtitle ("Pay ₹54,310 • No Cost EMI").
        $this->walk($state, function (string $key, mixed $node) use (&$out, &$seen) {
            if ($key === 'subtitle' && is_string($node) && stripos($node, 'no cost emi') !== false && ! isset($seen['emi'])) {
                $seen['emi'] = true;
                $out[] = Offers::make('emi', 'No Cost EMI', Text::clean($node));
            }
        });

        return $out;
    }

    /** Text of a {"value":{"contentList":[{"contentValue":"…"}]}} block. */
    private static function contentList(mixed $rc): string
    {
        $list = is_array($rc) ? ($rc['value']['contentList'] ?? []) : [];

        return implode(' ', array_filter(array_map(fn ($c) => is_array($c) && is_string($c['contentValue'] ?? null) ? $c['contentValue'] : '', (array) $list)));
    }

    /** Review cards in the "Ratings and reviews" block. @return list<array<string, mixed>> */
    private function stateReviews(array $state, int $max = 8): array
    {
        $out = [];
        $this->walk($state, function (string $key, mixed $node) use (&$out, $max) {
            if (! is_array($node) || ! isset($node['ratingData_0'], $node['customEllipsisData_0'], $node['label_3']) || count($out) >= $max) {
                return;
            }
            $body = (array) ($node['customEllipsisData_0']['value'] ?? []);
            $content = Text::clean((string) ($body['prependingText'] ?? '').(string) ($body['appendingText'] ?? ''));
            $author = Text::clean(self::text($node['label_3']));
            $k = $author.'|'.mb_substr($content, 0, 40);
            if ($content === '' || isset($out[$k])) {
                return;
            }
            $out[$k] = [
                'author' => $author,
                'rating' => (int) round((float) ($node['ratingData_0']['value']['rating'] ?? 0)),
                'title' => Text::clean(self::text($node['label_0'] ?? null)),
                'content' => Text::truncate($content, 600),
                'date' => Text::clean(self::text($node['label_1'] ?? null)),
            ];
        });

        return array_values($out);
    }

    /** schema.org reviews (older pages). @return list<array<string, mixed>> */
    private function ldReviews(array $ld): array
    {
        $out = [];
        foreach (array_slice((array) ($ld['review'] ?? []), 0, 8) as $r) {
            if (! is_array($r) || trim((string) ($r['reviewBody'] ?? '')) === '') {
                continue;
            }
            $out[] = [
                'author' => Text::clean((string) ($r['author']['name'] ?? '')),
                'rating' => (int) round((float) ($r['reviewRating']['ratingValue'] ?? 0)),
                'title' => Text::clean((string) ($r['name'] ?? '')),
                'content' => Text::truncate(Text::clean((string) $r['reviewBody']), 600),
                'date' => (string) ($r['datePublished'] ?? ''),
            ];
        }

        return $out;
    }

    /** Breadcrumb trail (Home › Footwear › Men's Footwear › …), without "Home" and the brand-filtered last step. @return list<string> */
    private function breadcrumbs(array $state): array
    {
        $out = [];
        $this->walk($state, function (string $key, mixed $node) use (&$out) {
            if ($out || $key !== 'horizontalListData_0' || ! is_array($node)) {
                return;
            }
            $trail = [];
            foreach ((array) ($node['value'] ?? []) as $item) {
                $label = (array) (($item['value'] ?? $item)['label_4'] ?? []);
                $url = (string) ($label['action']['url'] ?? '');
                $text = Text::clean(self::text($label));
                if ($text === '' || ($url !== '/' && ! str_contains($url, '/pr?'))) {
                    continue;
                }
                if ($url === '/' || str_contains($url, '~brand')) {
                    continue;
                }
                $trail[] = str_replace('’', "'", $text);
            }
            if (count($trail) >= 1) {
                $out = $trail;
            }
        });

        return array_values(array_unique($out));
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
