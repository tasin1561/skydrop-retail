<?php

namespace App\Sources\Parsers;

use App\Sources\Support\Offers;
use App\Sources\Support\Text;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Parses a raw amazon.in product page (as returned by Oxylabs amazon_product without parse) into the
 * internal product shape. Structured page parts are read with XPath; the JavaScript data blobs
 * (gallery, videos, variation matrix) with regex/JSON. Every part is optional.
 */
class AmazonHtmlParser
{
    private DOMXPath $x;

    private string $html;

    private string $decoded;

    /** @return array<string, mixed> */
    public function parse(string $html, string $asin, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $this->html = $html;
        $this->decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->x = $this->xpath($html);

        $price = $this->price();
        $mrp = $this->mrp() ?? $price;
        $specs = $this->specs();
        $stock = $this->text('//*[@id="availability"]//span[contains(@class,"a-size-medium") or contains(@class,"primary-availability-message")]')
            ?: $this->text('//*[@id="availability"]');
        $delivery = $this->delivery();
        $images = $this->gallery();
        $videos = $this->videos();
        [$offers, $dealBadge, $dealEndsAt, $coupon] = $this->offers($price, $mrp, $now);

        $inStock = $price > 0 && ! Text::isUnavailable($stock);

        return [
            'id' => $asin,
            'market' => 'amazon',
            'title' => $title = self::withBrand($this->text('//*[@id="productTitle"]'), $brand = $this->brand($specs), $this->text('//*[@id="bylineInfo"]')),
            'brand' => $brand,
            'price_inr' => $price,
            'mrp_inr' => max($mrp, $price),
            'discount_pct' => $this->discount(),
            'rating' => $this->rating(),
            'reviews' => Text::count($this->text('//*[@id="acrCustomerReviewText"]')),
            'image' => $images[0] ?? null,
            'images' => $images,
            'prime' => $this->prime(),
            'fba' => str_contains($this->html, 'isAmazonFulfilled=1'),
            'best_seller' => $this->x->query('//*[contains(@class,"best-seller-badge")]')->length > 0,
            'amazons_choice' => str_contains($this->text('//*[@id="acBadge_feature_div"]'), 'Choice'),
            'sales_volume' => $this->text('//*[contains(@id,"social-proofing-faceout-title")]'),
            'in_stock' => $inStock,
            'deliverable' => ! preg_match('/cannot be (?:delivered|shipped)|does not (?:deliver|ship)|not deliverable|currently unavailable/i', $delivery.' '.$stock),
            'stock_text' => $stock ?: null,
            'delivery_text' => $delivery ?: null,
            'bullets' => $this->bullets(),
            'specs' => $specs,
            'weight' => $this->weight($specs),
            'variants' => $this->variants($asin),
            'videos' => $videos,
            'has_videos' => $videos !== [],
            'aplus' => $this->aplus(),
            'review_list' => $this->reviews(),
            'star_breakdown' => $this->histogram(),
            'category_path' => $this->texts('//*[@id="wayfinding-breadcrumbs_feature_div"]//li//a'),
            'sales_rank' => $this->salesRank(),
            'parent_id' => preg_match('/"parentAsin"\s*:\s*"([A-Z0-9]{10})"/', $this->html, $m) ? $m[1] : null,
            'offers' => $offers,
            'deal_badge' => $dealBadge,
            'deal_ends_at' => $dealEndsAt?->toIso8601String(),
            'coupon_inr' => $coupon,
            'effective_price_inr' => Offers::effectivePrice($price, $coupon),
        ];
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        // Strip <script>/<style> bodies we never read through the DOM: halves parse time on 2 MB pages.
        $lean = preg_replace('#<(style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $lean = str_replace(['</p>', '<br>', '<br/>', '<br />'], [' </p>', ' ', ' ', ' '], $lean);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$lean, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return new DOMXPath($dom);
    }

    private function text(string $query, ?DOMNode $ctx = null): string
    {
        $n = $this->x->query($query, $ctx)?->item(0);

        return $n ? Text::clean($n->textContent) : '';
    }

    /** @return list<string> */
    private function texts(string $query, ?DOMNode $ctx = null): array
    {
        $out = [];
        foreach ($this->x->query($query, $ctx) ?: [] as $n) {
            $t = Text::clean($n->textContent);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }

    private function attr(string $query, string $name, ?DOMNode $ctx = null): string
    {
        $n = $this->x->query($query, $ctx)?->item(0);

        return $n instanceof DOMElement ? Text::clean($n->getAttribute($name)) : '';
    }

    private function price(): float
    {
        if (preg_match('/id="twister-plus-price-data-price"\s+value="([\d.]+)"/', $this->html, $m) && (float) $m[1] > 0) {
            return (float) $m[1];
        }
        foreach ([
            '//*[contains(@class,"priceToPay")]//*[@class="a-price-whole"]',
            '//*[@id="corePriceDisplay_desktop_feature_div"]//*[@class="a-price-whole"]',
            '//*[@id="corePrice_feature_div"]//*[@class="a-offscreen"]',
            '//*[@class="a-price-whole"]',
        ] as $q) {
            $v = Text::money($this->text($q));
            if ($v) {
                return $v;
            }
        }

        return 0.0;
    }

    private function mrp(): ?float
    {
        return Text::money($this->text('//*[contains(@class,"basisPrice")]//*[@class="a-offscreen"]'))
            ?? Text::money($this->text('//*[@data-a-strike="true"]//*[@class="a-offscreen"]'));
    }

    private function discount(): ?int
    {
        $t = $this->text('//*[contains(@class,"savingsPercentage")]');

        return preg_match('/(\d+)\s*%/', $t, $m) ? (int) $m[1] : null;
    }

    private function rating(): float
    {
        $t = $this->attr('//*[@id="acrPopover"]', 'title') ?: $this->text('//*[@id="acrPopover"]//*[@class="a-icon-alt"]');

        return preg_match('/([\d.]+)\s+out of/', $t, $m) ? (float) $m[1] : 0.0;
    }

    /** @param array<string, string> $specs */
    /** Fashion titles often leave the brand out ("New Cotton Leaf Printed Kurta" by GLAMALUE): put it in front. Not for books (author). */
    private static function withBrand(string $title, string $brand, string $byline): string
    {
        if ($title === '' || $brand === '' || preg_match('/^by\s/i', $byline) || preg_match('/^(generic|unbranded|na|n\/a)$/i', $brand)
            || mb_strlen($brand) > 30 || stripos($title, $brand) !== false || stripos($title, (string) strtok($brand, ' ')) === 0) {
            return $title;
        }

        return $brand.' '.$title;
    }

    private function brand(array $specs): string
    {
        $t = $this->text('//*[@id="bylineInfo"]');
        // Books: "by James Clear (Author) Format: Paperback" → "James Clear" (first contributor).
        if (preg_match('/^by\s+(.+?)(?:\s*\((?:Author|Editor|Illustrator)[^)]*\)|,|\s+and\s+|\s+Format:|$)/i', $t, $m)) {
            $t = trim($m[1]);
        }
        $t = preg_replace(['/^Visit the\s+/i', '/\s+Store$/i', '/^Brand:\s*/i', '/\s*Format:.*$/i'], '', $t) ?? $t;

        return $t !== '' ? $t : ($specs['Brand'] ?? $specs['Brand name'] ?? $specs['Manufacturer'] ?? '');
    }

    private function prime(): bool
    {
        return $this->x->query('//*[@id="buybox" or @id="desktop_buybox" or @id="apex_desktop"]//i[contains(@class,"a-icon-prime")]')->length > 0
            || (bool) preg_match('/"isPrimeEligible"\s*:\s*true/', $this->html)
            || str_contains($this->html, 'isAmazonFulfilled=1');
    }

    private function delivery(): string
    {
        $when = $this->attr('//*[starts-with(@id,"mir-layout-DELIVERY_BLOCK")]//*[@data-csa-c-delivery-time]', 'data-csa-c-delivery-time');
        if ($when !== '') {
            return $when;
        }

        return $this->text('//*[starts-with(@id,"mir-layout-DELIVERY_BLOCK")]') ?: $this->text('//*[@id="deliveryBlockMessage"]');
    }

    /** @return list<string> */
    private function bullets(): array
    {
        return array_values(array_filter(
            $this->texts('//*[@id="feature-bullets"]//li//span[contains(@class,"a-list-item")]'),
            fn ($b) => ! preg_match('/^(see more|show more)/i', $b),
        ));
    }

    /** @return array<string, string> */
    private function specs(): array
    {
        $specs = [];
        $put = function (string $k, string $v) use (&$specs) {
            $k = Text::label($k);
            $v = Text::clean($v);
            if ($k !== '' && $v !== '' && ! isset($specs[$k]) && ! in_array($k, ['Customer reviews', 'Asin'], true)) {
                $specs[$k] = Text::truncate($v, 500);
            }
        };
        foreach ($this->x->query('//table[contains(@class,"prodDetTable")]//tr') ?: [] as $tr) {
            $put($this->text('./th', $tr), $this->text('./td', $tr));
        }
        foreach ($this->x->query('//*[@id="productOverview_feature_div"]//tr') ?: [] as $tr) {
            $tds = $this->texts('./td', $tr);
            if (count($tds) >= 2) {
                $put($tds[0], $tds[1]);
            }
        }
        foreach ($this->x->query('//*[@id="detailBullets_feature_div"]//li') ?: [] as $li) {
            $k = $this->text('.//span[contains(@class,"a-text-bold")]', $li);
            $all = Text::clean($li->textContent);
            if ($k !== '') {
                $put($k, trim(mb_substr($all, mb_strlen($k)), " :\u{00A0}"));
            }
        }

        return $specs;
    }

    /** @param array<string, string> $specs */
    private function weight(array $specs): ?string
    {
        foreach ($specs as $k => $v) {
            if (stripos($k, 'weight') !== false && preg_match('/[\d.]+\s*(kg|kilo|g\b|gram)/i', $v)) {
                return $v;
            }
        }
        foreach (['Net quantity', 'Product dimensions'] as $k) {
            if (isset($specs[$k]) && preg_match('/([\d.]+\s*(?:kg|kilograms?|g|grams?))\b/i', $specs[$k], $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /** @return list<array{rating: int, rank: int, category: string}> */
    private function salesRank(): array
    {
        $out = [];
        foreach ($this->x->query('//th[contains(normalize-space(.),"Best Sellers Rank")]/following-sibling::td//li') ?: [] as $li) {
            if (preg_match('/#([\d,]+)\s+in\s+([^(]+)/', Text::clean($li->textContent), $m)) {
                $out[] = ['rank' => (int) str_replace(',', '', $m[1]), 'category' => trim($m[2])];
            }
        }

        return $out;
    }

    /** Hi-res gallery for the landing colour. @return list<string> */
    private function gallery(): array
    {
        $images = [];
        $pos = strpos($this->html, "'colorImages': { 'initial':");
        if ($pos !== false) {
            $start = strpos($this->html, '[', $pos);
            foreach ((array) ($start !== false ? $this->decodeJsonAt($this->html, $start) : []) as $img) {
                $u = $img['hiRes'] ?? $img['large'] ?? null;
                if ($u) {
                    $images[] = $u;
                }
            }
        }
        if (! $images) {
            $u = $this->attr('//*[@id="landingImage"]', 'data-old-hires') ?: $this->attr('//*[@id="landingImage"]', 'src');
            if ($u) {
                $images[] = $u;
            }
        }

        return array_values(array_unique($images));
    }

    /** @return list<array{hls: string, thumb: ?string}> */
    private function videos(): array
    {
        $out = [];
        if (! preg_match_all('/"videoURL"\s*:\s*"(https:[^"]+?\.m3u8)"/', $this->decoded, $mm, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        foreach ($mm[1] as [$url, $pos]) {
            if (isset($out[$url])) {
                continue;
            }
            // The thumbnail sits in the same JSON object, just before the URL.
            $window = substr($this->decoded, max(0, $pos - 1500), 1500);
            $thumb = preg_match_all('/"videoImageUrlUnchanged"\s*:\s*"([^"]+)"/', $window, $t) ? end($t[1]) : null;
            $out[$url] = ['hls' => str_replace('\\/', '/', $url), 'thumb' => $thumb ? str_replace('\\/', '/', $thumb) : null];
        }

        return array_values($out);
    }

    /** @return list<string> */
    private function aplus(): array
    {
        $out = [];
        foreach ($this->x->query('//*[@id="aplus" or @id="aplus_feature_div" or @id="aplusBrandStory_feature_div" or contains(@class,"aplus-v2")]//img') ?: [] as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            if (str_contains($src, 'aplus-media') && ! str_contains($src, 'grey-pixel')) {
                $out[$src] = true;
            }
        }

        return array_keys($out);
    }

    /** @return list<array{author: string, rating: int, title: string, content: string, date: string}> */
    private function reviews(int $max = 8): array
    {
        $out = [];
        foreach ($this->x->query('//*[@data-hook="review"]') ?: [] as $r) {
            $rating = $this->text('.//*[@data-hook="review-star-rating" or @data-hook="cmps-review-star-rating"]', $r);
            $title = $this->text('.//*[@data-hook="reviewTitle" or @data-hook="review-title"]', $r);
            $title = trim(preg_replace('/^[\d.]+ out of 5 stars\s*/', '', $title) ?? $title);
            $body = $this->text('.//*[@data-hook="reviewRichContentContainer" or @data-hook="review-body"]', $r);
            $author = $this->text('.//*[contains(@class,"a-profile-name")]', $r);
            $key = $author.'|'.$title;
            if ($body === '' || isset($out[$key])) {
                continue;
            }
            $out[$key] = [
                'author' => $author,
                'rating' => preg_match('/([\d.]+)\s+out of/', $rating, $m) ? (int) round((float) $m[1]) : 0,
                'title' => $title,
                'content' => Text::truncate(preg_replace('/\s*Read more\s*$/', '', $body) ?? $body, 600),
                'date' => $this->text('.//*[@data-hook="review-date"]', $r),
            ];
            if (count($out) >= $max) {
                break;
            }
        }

        return array_values($out);
    }

    /** @return list<array{rating: int, percentage: int}> */
    private function histogram(): array
    {
        $out = [];
        foreach ($this->x->query('//*[@id="histogramTable"]//a[@aria-label]') ?: [] as $a) {
            /** @var DOMElement $a */
            if (preg_match('/(\d+)\s*percent of reviews have (\d) star/', $a->getAttribute('aria-label'), $m)) {
                $out[(int) $m[2]] = ['rating' => (int) $m[2], 'percentage' => (int) $m[1]];
            }
        }
        krsort($out);

        return array_values($out);
    }

    /**
     * Variation matrix: every child ASIN with its dimension values, a colour swatch where we have one,
     * and the landing ASIN marked selected.
     *
     * @return list<array{id: string, label: string, dimensions: array<string, string>, swatch: ?string, selected: bool}>
     */
    private function variants(string $asin): array
    {
        $display = $this->jsonAfter('"dimensionValuesDisplayData"');
        if (! is_array($display) || $display === []) {
            return [];
        }
        $dims = $this->jsonAfter('"dimensions"');
        $dims = is_array($dims) && array_is_list($dims) ? $dims : [];
        $labels = $this->jsonAfter('"variationDisplayLabels"');
        $labels = is_array($labels) ? $labels : [];
        $names = array_map(fn ($d) => $labels[$d] ?? Text::label(str_replace('_name', '', $d)), $dims);
        $swatches = $this->colourSwatches();

        $out = [];
        foreach ($display as $childAsin => $values) {
            $values = array_values((array) $values);
            $dimensions = [];
            foreach ($values as $i => $v) {
                $dimensions[$names[$i] ?? 'Option '.($i + 1)] = (string) $v;
            }
            $swatch = null;
            foreach ($values as $v) {
                $swatch ??= $swatches[$v] ?? null;
            }
            $out[] = [
                'id' => (string) $childAsin,
                'label' => implode(' / ', array_map('strval', $values)),
                'dimensions' => $dimensions,
                'swatch' => $swatch,
                'selected' => $childAsin === $asin,
            ];
        }

        return $out;
    }

    /** Per-colour main image from the ImageBlockBTF "colorImages" object. @return array<string, string> */
    private function colourSwatches(): array
    {
        $pos = strpos($this->html, '"landingAsinColor"');
        if ($pos === false) {
            return [];
        }
        $start = strpos($this->html, '"colorImages":', $pos);
        if ($start === false || $start - $pos > 2000) {
            return [];
        }
        $obj = $this->decodeJsonAt($this->html, $start + strlen('"colorImages":'));
        $out = [];
        foreach (is_array($obj) ? $obj : [] as $colour => $imgs) {
            $main = collect((array) $imgs)->firstWhere('variant', 'MAIN') ?? ($imgs[0] ?? null);
            $u = $main['thumb'] ?? $main['large'] ?? null;
            if ($u) {
                $out[(string) $colour] = preg_replace('/\._[A-Z]{2}\d+_\.(jpg|png)$/', '.$1', $u) === $u
                    ? preg_replace('/\.(jpg|png)$/', '._SS64_.$1', $u)
                    : preg_replace('/\._[A-Z]{2}\d+_\.(jpg|png)$/', '._SS64_.$1', $u);
            }
        }

        return $out;
    }

    /** Decode the JSON value that follows "key" : in the page. */
    private function jsonAfter(string $key): mixed
    {
        $pos = strpos($this->html, $key);
        if ($pos === false) {
            return null;
        }
        $colon = strpos($this->html, ':', $pos + strlen($key));

        return $colon === false ? null : $this->decodeJsonAt($this->html, $colon + 1);
    }

    /** Decode one balanced JSON object/array starting at (or just after whitespace from) $offset. */
    private function decodeJsonAt(string $s, int $offset): mixed
    {
        $len = strlen($s);
        while ($offset < $len && ctype_space($s[$offset])) {
            $offset++;
        }
        $open = $s[$offset] ?? '';
        if ($open !== '{' && $open !== '[') {
            return null;
        }
        $close = $open === '{' ? '}' : ']';
        $depth = 0;
        $inStr = false;
        for ($i = $offset; $i < $len; $i++) {
            $c = $s[$i];
            if ($inStr) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === '"') {
                    $inStr = false;
                }

                continue;
            }
            if ($c === '"') {
                $inStr = true;
            } elseif ($c === $open) {
                $depth++;
            } elseif ($c === $close && --$depth === 0) {
                return json_decode(substr($s, $offset, $i - $offset + 1), true);
            }
        }

        return null;
    }

    /**
     * Coupons, deal badges, lightning deals, the offers carousel, Subscribe & Save and promotions.
     *
     * @return array{0: list<array<string, mixed>>, 1: ?string, 2: ?CarbonImmutable, 3: ?float}
     */
    private function offers(float $price, float $mrp, CarbonImmutable $now): array
    {
        $offers = [];
        $coupon = null;

        // Deal badge ("Limited time deal", "Lightning Deal", "Prime Deal", festival deals).
        $badge = $this->text('//*[@id="dealBadgeSupportingText"]')
            ?: $this->text('//*[@id="dealBadge_feature_div"]//*[contains(@class,"dealBadge")]//span[not(contains(@class,"aok-hidden"))][last()]');
        $badge = $badge !== '' ? $badge : null;
        $dealBox = $this->text('//*[@id="dealBadge_feature_div"]').' '.$this->text('//*[@id="deal_expiry_timer_feature_div" or contains(@id,"dealCountdown") or contains(@class,"dealCountdown")]');
        $endsAt = Offers::endsIn($dealBox, $now);
        if (! $endsAt && preg_match('/"(?:dealEndTime|endTime|msToEnd)"\s*:\s*"?(\d{10,13})"?/', $this->html, $m)) {
            $endsAt = strlen($m[1]) >= 13 && str_contains($m[0], 'msToEnd')
                ? $now->addMilliseconds((int) $m[1])
                : CarbonImmutable::createFromTimestamp(strlen($m[1]) === 13 ? intdiv((int) $m[1], 1000) : (int) $m[1]);
        }
        $claimed = preg_match('/(\d{1,3})\s*%\s*claimed/i', $dealBox, $m) ? (int) $m[1] : null;
        $isLightning = $badge && stripos($badge, 'lightning') !== false || stripos($dealBox, 'lightning') !== false;
        if ($badge || $isLightning) {
            $badge ??= 'Lightning Deal';
            $offers[] = Offers::make($isLightning ? 'lightning' : 'deal', $badge,
                $mrp > $price ? sprintf('Deal price ₹%s (M.R.P. ₹%s)', number_format($price), number_format($mrp)) : null,
                $mrp > $price ? $mrp - $price : null, $endsAt, $claimed);
        }

        // Clippable coupon of THIS product: only the price block / buy box, never carousels of other products
        // (those say "You pay ₹38,999.00 with coupon" for a different item).
        $own = '[not(ancestor::*[contains(@class,"a-carousel") or contains(@id,"sims") or contains(@id,"sp_detail") or contains(@id,"similarities") or contains(@id,"session-sims") or contains(@data-cel-widget,"sp_")])]';
        $texts = [$this->text('//*[@id="promoPriceBlockMessage_feature_div"]')];
        foreach ($this->texts('//*[contains(@id,"couponText") or contains(@class,"couponLabelText") or contains(@id,"couponBadge") or contains(@class,"couponBadge") or contains(@id,"vpcButton")]'.$own) as $t) {
            $texts[] = $t;
        }
        foreach ($texts as $t) {
            [$value, $label] = self::couponFrom($t, $price);
            if ($value !== null) {
                $coupon = $value;
                $offers[] = Offers::make('coupon', $label, null, $coupon);
                break;
            }
        }

        // "Save extra" promotions next to the price (multi-buy, extra % off): informational.
        foreach ($this->texts('//*[@id="promoPriceBlockMessage_feature_div"]//*[contains(@class,"promotion") or contains(@id,"promoMessage")]') as $promo) {
            if (stripos($promo, 'coupon') === false) {
                $offers[] = Offers::make(Offers::classify($promo) === 'combo' ? 'combo' : 'other', $promo, null, Offers::rupees($promo), applies: false);
            }
        }

        // Offers carousel: Cashback, Bank Offer, No Cost EMI, Partner Offers...
        foreach ($this->x->query('//*[@id="vsxoffers_feature_div" or @id="poToCart_feature_div" or @id="itembox-InstantBankDiscount"]//*[starts-with(@id,"itembox-")]') ?: [] as $box) {
            $title = $this->text('.//*[contains(@class,"offers-items-title")]', $box);
            $detail = $this->text('.//*[contains(@class,"a-truncate-full")]', $box) ?: $this->text('.//*[contains(@class,"offers-items-content")]', $box);
            if ($title === '') {
                continue;
            }
            $offers[] = Offers::make(Offers::classify($title), $title, $detail, Offers::rupees($detail));
        }

        // Exchange offer box (phones, appliances).
        $exchange = $this->text('//*[contains(@id,"exchangeOffer") or contains(@id,"tradeIn")]//*[contains(@class,"a-text-bold") or contains(@class,"a-color-price")]');
        if ($exchange !== '') {
            $offers[] = Offers::make('exchange', 'Exchange offer', $exchange, Offers::rupees($exchange));
        }

        // Subscribe & Save inside the buy box.
        $sns = $this->text('//*[@id="snsAccordionRowMiddle" or @id="sns-base-price" or contains(@id,"snsPrice")]');
        if ($sns !== '') {
            $offers[] = Offers::make('subscribe', 'Subscribe & Save', $sns, null);
        }

        // The offers carousel is rendered twice on some pages (desktop + accessibility copy).
        $offers = array_values(collect($offers)->unique(fn ($o) => $o['type'].'|'.$o['title'].'|'.($o['detail'] ?? ''))->all());

        return [$offers, $badge, $endsAt, $coupon];
    }

    /**
     * The coupon value in INR from a coupon phrase only (never another % on the line, like "80% Savings"):
     * "Apply ₹500 coupon", "Apply 2% coupon", "Save ₹50 with coupon", "Save 10% on coupon", "You pay ₹1,299 with coupon".
     * Values above 60% of the price are treated as a misread and ignored.
     *
     * @return array{0: ?float, 1: string}
     */
    public static function couponFrom(string $text, float $price): array
    {
        $t = Text::clean($text);
        $money = fn (string $v) => (float) str_replace(',', '', $v);
        $value = null;
        $label = '';
        if (preg_match('/(Apply\s+₹\s*([\d,]+(?:\.\d+)?)\s+coupon)/iu', $t, $m) || preg_match('/(Save\s+₹\s*([\d,]+(?:\.\d+)?)\s+(?:with|on)\s+coupon)/iu', $t, $m)) {
            [$label, $value] = [$m[1], $money($m[2])];
        } elseif (preg_match('/(Apply\s+(\d+(?:\.\d+)?)\s*%\s+coupon)/iu', $t, $m) || preg_match('/(Save\s+(\d+(?:\.\d+)?)\s*%\s+(?:with|on)\s+coupon)/iu', $t, $m)) {
            [$label, $value] = [$m[1], round($price * (float) $m[2] / 100, 2)];
        } elseif (preg_match('/(You pay\s+₹\s*([\d,]+(?:\.\d+)?)\s+with coupon)/iu', $t, $m) && $money($m[2]) < $price) {
            [$label, $value] = [$m[1], round($price - $money($m[2]), 2)];
        }
        if ($value === null || $value <= 0 || $price <= 0 || $value > 0.6 * $price) {
            return [null, ''];
        }

        return [$value, $label];
    }
}
