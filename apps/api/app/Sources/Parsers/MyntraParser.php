<?php

namespace App\Sources\Parsers;

use App\Sources\Support\Offers;
use App\Sources\Support\SearchPage;
use App\Sources\Support\Text;

/**
 * myntra.com pages. Both listing and product pages carry their data in `window.__myx = {...}`:
 * - listing / search: searchData.results.products[] {productId, productName, brand, price, mrp, rating, ratingCount,
 *   searchImage, inventoryInfo, sizes, couponData, articleType, masterCategory, subCategory}
 * - product (needs JS rendering, plain fetches are refused): pdpData {id, name, brand.name, price.{mrp,discounted},
 *   sizes[] {label, available, sizeSellerData}, colours[] {styleId, label, image}, media, ratings, productDetails,
 *   articleAttributes, analytics, flags}
 *
 * Our ids are "my" + the Myntra style id. Other colours are separate styles (fetchable variants); sizes are options
 * of the same style, so they are variants without an id: the size goes into the cart as the option label.
 */
class MyntraParser
{
    public static function styleId(string $id): ?string
    {
        return preg_match('/^(?:my)?(\d{5,10})$/i', trim($id), $m) ? $m[1] : null;
    }

    public static function idFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('#/(\d{5,10})(?:/buy)?/?$#', $path, $m) ? 'my'.$m[1] : null;
    }

    public static function productUrl(string $id): string
    {
        return 'https://www.myntra.com/'.(self::styleId($id) ?? rawurlencode($id));
    }

    /** @return array<string, mixed>|null */
    public static function state(string $html): ?array
    {
        $i = strpos($html, 'window.__myx');
        $start = $i === false ? false : strpos($html, '{', $i);
        if ($start === false) {
            return null;
        }
        // The object is followed by more script, so find its closing brace (skipping braces inside strings).
        $depth = 0;
        $inString = false;
        $len = strlen($html);
        for ($k = $start; $k < $len; $k++) {
            $c = $html[$k];
            if ($inString) {
                if ($c === '\\') {
                    $k++;
                } elseif ($c === '"') {
                    $inString = false;
                }

                continue;
            }
            if ($c === '"') {
                $inString = true;
            } elseif ($c === '{') {
                $depth++;
            } elseif ($c === '}' && --$depth === 0) {
                $d = json_decode(substr($html, $start, $k - $start + 1), true);

                return is_array($d) ? $d : null;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function product(string $html, string $id): array
    {
        $p = (array) (self::state($html)['pdpData'] ?? []);
        $styleId = (string) ($p['id'] ?? self::styleId($id) ?? '');
        $price = Text::money($p['price']['discounted'] ?? null) ?? Text::money($p['selectedSeller']['discountedPrice'] ?? null) ?? 0.0;
        $mrp = Text::money($p['price']['mrp'] ?? $p['mrp'] ?? null) ?? $price;
        $sizes = array_values(array_filter((array) ($p['sizes'] ?? []), 'is_array'));
        $flags = (array) ($p['flags'] ?? []);
        $inStock = $price > 0 && ! ($flags['outOfStock'] ?? false) && ! ($flags['disableBuyButton'] ?? false)
            && ($sizes === [] || collect($sizes)->contains(fn ($s) => (bool) ($s['available'] ?? false)));
        $analytics = (array) ($p['analytics'] ?? []);
        $brand = Text::clean((string) ($p['brand']['name'] ?? $analytics['brand'] ?? ''));
        $title = Text::clean((string) ($p['name'] ?? ''));
        $images = $this->images($p);
        $videos = [];
        foreach ((array) ($p['media']['videos'] ?? []) as $v) {
            $url = (string) ($v['url'] ?? '');
            if (str_starts_with($url, 'https://') && str_contains($url, '.m3u8')) {
                $videos[] = ['hls' => $url, 'thumb' => $v['thumbnail'] ?? null];
            }
        }
        $ratings = (array) ($p['ratings'] ?? []);
        $total = (int) ($ratings['totalCount'] ?? 0);
        $stars = [];
        foreach ((array) ($ratings['ratingInfo'] ?? []) as $r) {
            if ($total > 0 && isset($r['rating'])) {
                $stars[(int) $r['rating']] = ['rating' => (int) $r['rating'], 'percentage' => (int) round(100 * (int) ($r['count'] ?? 0) / $total)];
            }
        }
        krsort($stars);
        $discount = collect((array) ($p['discounts'] ?? []))->first(fn ($d) => is_array($d) && ($d['label'] ?? '') !== '');
        $offers = [];
        if ($mrp > $price && $price > 0) {
            $offers[] = Offers::make('deal', trim(Text::clean((string) ($discount['label'] ?? '')), '()') ?: 'Sale price', null, $mrp - $price);
        }
        $badge = $discount ? trim(Text::clean((string) $discount['label']), '()') : null;

        return [
            'id' => $id,
            'market' => 'myntra',
            'title' => self::withBrand($title, $brand),
            'brand' => $brand !== '' ? $brand : null,
            'price_inr' => $price,
            'mrp_inr' => max($mrp, $price),
            'rating' => round((float) ($ratings['averageRating'] ?? 0), 1),
            'reviews' => $total,
            'image' => $images[0] ?? null,
            'images' => $images,
            'prime' => false, 'best_seller' => false, 'amazons_choice' => false, 'sales_volume' => '',
            'in_stock' => $inStock,
            // Fetched without a delivery pincode; delivery to the Kolkata hub is confirmed when we buy.
            'deliverable' => true,
            'stock_text' => $inStock ? 'In stock' : 'Out of stock',
            'delivery_text' => null,
            'bullets' => $this->bullets($p),
            'specs' => $this->specs($p, $brand),
            'weight' => null, // Myntra doesn't publish weights: the pricing category's default weight is used
            'variants' => $this->variants($p, $id, $styleId),
            'videos' => array_slice($videos, 0, 6),
            'has_videos' => $videos !== [],
            'aplus' => [],
            'review_list' => $this->reviews($ratings),
            'star_breakdown' => array_values($stars),
            'category_path' => self::path($analytics['gender'] ?? null, $analytics['masterCategory'] ?? null, $analytics['subCategory'] ?? null, $analytics['articleType'] ?? null),
            'offers' => $offers,
            'deal_badge' => $badge ?: null,
            'deal_ends_at' => null,
            'coupon_inr' => null,
            'effective_price_inr' => null,
        ];
    }

    public function listing(string $html): SearchPage
    {
        $res = (array) (self::state($html)['searchData']['results'] ?? []);
        $rows = [];
        foreach ((array) ($res['products'] ?? []) as $n) {
            $row = is_array($n) ? $this->card($n) : null;
            if ($row && $row['price_inr'] > 0) {
                $rows[$row['id']] = $row;
            }
        }
        $total = (int) ($res['totalCount'] ?? count($rows));
        $perPage = max(1, count((array) ($res['products'] ?? [])));

        return new SearchPage(array_values($rows), $total, ($res['hasNextPage'] ?? false) ? max(2, (int) ceil($total / $perPage)) : 1);
    }

    /**
     * @param  array<string, mixed>  $n
     * @return array<string, mixed>|null
     */
    public function card(array $n): ?array
    {
        $style = (string) ($n['productId'] ?? '');
        if (! preg_match('/^\d{5,10}$/', $style)) {
            return null;
        }
        $price = Text::money($n['price'] ?? null) ?? 0.0;
        $mrp = Text::money($n['mrp'] ?? null) ?? $price;
        $brand = Text::clean((string) ($n['brand'] ?? ''));
        $image = self::https((string) ($n['searchImage'] ?? ''));
        $inventory = (array) ($n['inventoryInfo'] ?? []);
        $badge = trim(Text::clean((string) ($n['discountDisplayLabel'] ?? '')), '()');

        return [
            'id' => 'my'.$style,
            'market' => 'myntra',
            'title' => self::withBrand(Text::clean((string) ($n['productName'] ?? $n['product'] ?? '')), $brand),
            'brand' => $brand !== '' ? $brand : null,
            'price_inr' => $price,
            'mrp_inr' => max($mrp, $price),
            'rating' => round((float) ($n['rating'] ?? 0), 1),
            'reviews' => (int) ($n['ratingCount'] ?? 0),
            'image' => $image !== '' ? $image : null,
            'in_stock' => $inventory === [] || collect($inventory)->contains(fn ($i) => is_array($i) && ($i['available'] ?? false)),
            'category_path' => self::path($n['gender'] ?? null, $n['masterCategory']['typeName'] ?? null, $n['subCategory']['typeName'] ?? null, $n['articleType']['typeName'] ?? $n['category'] ?? null),
            'offers' => $mrp > $price && $price > 0 ? [Offers::make('deal', $badge !== '' ? $badge : 'Sale price', null, $mrp - $price)] : [],
            'deal_badge' => $badge !== '' ? $badge : null,
            'prime' => false, 'best_seller' => false, 'amazons_choice' => false, 'sales_volume' => '',
        ];
    }

    /**
     * Other colours (separate styles, fetchable) + this style's sizes (no id: picked as an option).
     *
     * @param  array<string, mixed>  $p
     * @return list<array<string, mixed>>
     */
    private function variants(array $p, string $id, string $styleId): array
    {
        $colour = Text::clean((string) ($p['baseColour'] ?? ''));
        $out = [];
        $colours = array_values(array_filter((array) ($p['colours'] ?? []), fn ($c) => is_array($c) && ! empty($c['styleId'])));
        if ($colours !== [] && $colour !== '') {
            foreach ($colours as $c) {
                if ((string) $c['styleId'] === $styleId) {
                    continue;
                }
                $label = Text::clean((string) ($c['label'] ?? '')) ?: 'Colour '.(count($out) + 2);
                // Two styles can share a colour name ("Pink / White"): number them so both stay pickable.
                $base = $label;
                for ($n = 2; $label === $colour || in_array($label, array_column($out, 'label'), true); $n++) {
                    $label = "$base ($n)";
                }
                $out[] = ['id' => 'my'.$c['styleId'], 'label' => $label, 'dimensions' => ['Colour' => $label],
                    'swatch' => self::https((string) ($c['image'] ?? '')) ?: null, 'selected' => false, 'in_stock' => true];
            }
        }
        $sizes = array_values(array_filter((array) ($p['sizes'] ?? []), fn ($s) => is_array($s) && ($s['label'] ?? '') !== ''));
        $one = count($sizes) === 1 && preg_match('/^(one ?size|onesize|free ?size|na|std)$/i', (string) $sizes[0]['label']);
        if ($sizes === [] || $one) {
            // Only other colours: list this one too so the colour picker shows the current choice.
            return $out === [] ? [] : [['id' => $id, 'label' => $colour, 'dimensions' => ['Colour' => $colour], 'swatch' => null, 'selected' => true, 'in_stock' => true], ...$out];
        }
        $firstAvailable = collect($sizes)->search(fn ($s) => (bool) ($s['available'] ?? false));
        foreach ($sizes as $i => $s) {
            $label = Text::clean((string) $s['label']);
            $dims = $out !== [] && $colour !== '' ? ['Colour' => $colour, 'Size' => $label] : ['Size' => $label];
            $out[] = ['id' => null, 'label' => implode(' / ', $dims), 'dimensions' => $dims, 'swatch' => null,
                'selected' => $i === $firstAvailable, 'in_stock' => (bool) ($s['available'] ?? false)];
        }

        return $out;
    }

    /** @param array<string, mixed> $p @return list<string> */
    private function images(array $p): array
    {
        $out = [];
        foreach ((array) ($p['media']['albums'] ?? []) as $album) {
            if (! is_array($album) || ($album['name'] ?? '') !== 'default') {
                continue;
            }
            foreach ((array) ($album['images'] ?? []) as $img) {
                $u = self::https((string) ($img['imageURL'] ?? ''));
                if ($u !== '') {
                    $out[] = $u;
                }
            }
        }

        return array_values(array_unique(array_slice($out, 0, 20)));
    }

    /** @param array<string, mixed> $p @return list<string> */
    private function bullets(array $p): array
    {
        $out = [];
        foreach ((array) ($p['productDetails'] ?? []) as $d) {
            $text = (string) ($d['description'] ?? '');
            if ($text === '') {
                continue;
            }
            $text = (string) preg_replace('#<\s*(br|/li|/p)\s*/?>#i', "\n", $text);
            foreach (preg_split('/\n+/', strip_tags($text)) ?: [] as $line) {
                $line = Text::clean($line);
                if (mb_strlen($line) > 2 && ! preg_match('/^features:?$/i', $line)) {
                    $out[] = Text::truncate($line, 400);
                }
            }
        }

        return array_values(array_unique(array_slice($out, 0, 12)));
    }

    /** @param array<string, mixed> $p @return array<string, string> */
    private function specs(array $p, string $brand): array
    {
        $specs = $brand !== '' ? ['Brand' => $brand] : [];
        foreach ((array) ($p['articleAttributes'] ?? []) as $k => $v) {
            $v = Text::clean(is_scalar($v) ? (string) $v : '');
            if ($v !== '' && ! in_array(strtoupper($v), ['NA', 'N/A'], true) && ! preg_match('/^body or garment size$/i', (string) $k)) {
                $specs[Text::label((string) $k)] = $v;
            }
        }
        if (! empty($p['baseColour'])) {
            $specs['Colour'] = Text::clean((string) $p['baseColour']);
        }
        if (! empty($p['countryOfOrigin'])) {
            $specs['Country of origin'] = Text::clean((string) $p['countryOfOrigin']);
        }
        $returns = (int) ($p['serviceability']['returnPeriod'] ?? 0);
        if ($returns > 0) {
            $specs['Myntra returns'] = "$returns days";
        }

        return $specs;
    }

    /** @param array<string, mixed> $ratings @return list<array<string, mixed>> */
    private function reviews(array $ratings): array
    {
        $out = [];
        foreach ((array) ($ratings['reviewInfo']['topReviews'] ?? []) as $r) {
            $content = Text::clean((string) ($r['reviewText'] ?? ''));
            if (! is_array($r) || $content === '') {
                continue;
            }
            $ts = (int) ($r['timestamp'] ?? 0);
            $out[] = [
                'author' => Text::clean((string) ($r['userName'] ?? '')),
                'rating' => (int) ($r['userRating'] ?? 0),
                'title' => '',
                'content' => Text::truncate($content, 600),
                'date' => $ts > 0 ? date('j M Y', intdiv($ts, 1000)) : '',
            ];
        }

        return array_slice($out, 0, 10);
    }

    /**
     * Breadcrumbs for our category matcher from Myntra's gender + master/sub/article categories. Myntra's own crumbs
     * have no gender, which put a girls' kurta under men's kurtas: men/women go first, and kids items get the exact
     * names of our kids nodes ("Girls Clothing", "Kids Footwear").
     *
     * @return list<string>
     */
    public static function path(mixed $gender, mixed $master, mixed $sub, mixed $article): array
    {
        $clean = fn ($v) => Text::clean(is_string($v) ? $v : '');
        [$g, $m, $sb, $a] = [$clean($gender), $clean($master), $clean($sub), $clean($article)];
        $kid = preg_match('/^(boys|girls|kids|unisex kids|baby)/i', $g);
        $parts = match (true) {
            $kid && preg_match('/footwear/i', $m) === 1 => ['Kids', $m, 'Kids Footwear'],
            (bool) $kid => ['Kids', $m, $sb, $a, preg_match('/^(boys|girls)/i', $g, $mm) ? ucfirst(strtolower($mm[1])).' Clothing' : ''],
            (bool) preg_match('/^(men|women)$/i', $g) => [ucfirst(strtolower($g)), $m, $sb, $a],
            default => [$m, $sb, $a],
        };

        return array_values(array_filter($parts, fn ($x) => $x !== ''));
    }

    /** Myntra names usually start with the brand; add it when they don't. */
    public static function withBrand(string $title, string $brand): string
    {
        if ($brand === '' || $title === '' || mb_strlen($brand) > 30 || str_starts_with(mb_strtolower($title), mb_strtolower($brand))) {
            return $title;
        }

        return $brand.' '.$title;
    }

    private static function https(string $u): string
    {
        return $u === '' ? '' : (string) preg_replace('#^http://#', 'https://', $u);
    }
}
