<?php

namespace App\Services\Shopping;

use App\Models\Product;
use App\Models\ProductMatch;
use Illuminate\Support\Collection;

/**
 * Finds listings of the same product: GTIN/EAN/UPC first, then model number, then normalised brand +
 * title trigram similarity (same category). Pairs are stored in product_matches (product_id < match_id).
 * Admin decisions (confirmed / rejected) are never overwritten by a re-run.
 */
class ProductMatcher
{
    /** Title matches at or above this score are shown without review; lower ones wait for the admin. */
    public const AUTO_SHOW = 0.82;

    public const MIN_TITLE = 0.62;

    private const GTIN_KEYS = '/\b(gtin|ean|upc|isbn|barcode)\b/i';

    private const MODEL_KEYS = '/\b(model\s*(number|no\.?|name)?|item model number|part number|mpn)\b/i';

    private const STOP = ['with', 'and', 'for', 'the', 'pack', 'of', 'new', 'latest', 'edition', 'black', 'white', 'blue', 'red', 'green', 'grey', 'gray', 'silver', 'gold', 'pink'];

    /** Matches every product (grouped by brand). Returns the number of pairs written. */
    public function run(?string $onlyBrand = null, int $maxGroup = 400): int
    {
        $written = 0;
        $q = Product::query()->with('details')->whereNotNull('brand')->where('brand', '!=', '')->where('hidden', false);
        $groups = $q->get()->groupBy(fn (Product $p) => self::normBrand((string) $p->brand));
        foreach ($groups as $brand => $list) {
            if ($brand === '' || $list->count() < 2 || ($onlyBrand !== null && $brand !== self::normBrand($onlyBrand))) {
                continue;
            }
            $written += $this->matchGroup($list->take($maxGroup)->values());
        }

        return $written;
    }

    /** Matches one product against the rest of its brand. */
    public function forProduct(Product $p): int
    {
        if (! $p->brand) {
            return 0;
        }
        $brand = self::normBrand((string) $p->brand);
        $list = Product::query()->with('details')->where('hidden', false)->where('id', '!=', $p->id)
            ->whereRaw('lower(brand) like ?', ['%'.mb_strtolower(mb_substr((string) $p->brand, 0, 30)).'%'])->limit(400)->get()
            ->filter(fn (Product $o) => self::normBrand((string) $o->brand) === $brand);

        return $this->matchGroup(collect([$p])->concat($list)->values(), $p->id);
    }

    /** @param Collection<int, Product> $list */
    private function matchGroup(Collection $list, ?string $anchor = null): int
    {
        $sig = $list->map(fn (Product $p) => ['p' => $p] + $this->signature($p))->all();
        $n = count($sig);
        $written = 0;
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($anchor !== null && $sig[$i]['p']->id !== $anchor && $sig[$j]['p']->id !== $anchor) {
                    continue;
                }
                $m = $this->compare($sig[$i], $sig[$j]);
                if ($m) {
                    $written += $this->store($sig[$i]['p']->id, $sig[$j]['p']->id, $m['method'], $m['score']);
                }
            }
        }

        return $written;
    }

    /** @return array{method: string, score: float}|null */
    public function compare(array $a, array $b): ?array
    {
        $pa = $a['p'];
        $pb = $b['p'];
        if ($pa->id === $pb->id || ($pa->parent_id && $pa->parent_id === $pb->parent_id)) {
            return null; // variants of one listing are not "other stores"
        }
        if ($a['gtin'] && $b['gtin']) {
            return array_intersect($a['gtin'], $b['gtin']) ? ['method' => 'gtin', 'score' => 1.0] : null; // different barcodes = different products
        }
        if ($a['models'] && $b['models'] && array_intersect($a['models'], $b['models'])) {
            // Same model number; check the size/quantity does not contradict ("128GB" vs "256GB").
            if (self::sizesConflict($a['sizes'], $b['sizes'])) {
                return null;
            }

            return ['method' => 'model', 'score' => 0.95];
        }
        if ($pa->category !== $pb->category) {
            return null;
        }
        if (self::sizesConflict($a['sizes'], $b['sizes'])) {
            return null;
        }
        $score = self::similarity($a['title'], $b['title']);

        return $score >= self::MIN_TITLE ? ['method' => 'title', 'score' => round($score, 3)] : null;
    }

    /** @return array{gtin: list<string>, models: list<string>, sizes: list<string>, title: string} */
    public function signature(Product $p): array
    {
        $specs = (array) ($p->details?->specs ?? []);
        $gtin = [];
        $models = [];
        foreach ($specs as $k => $v) {
            $v = is_scalar($v) ? (string) $v : '';
            if (preg_match(self::GTIN_KEYS, (string) $k)) {
                foreach (preg_split('/[\s,;\/]+/', $v) as $code) {
                    $code = preg_replace('/\D/', '', $code);
                    if (strlen($code) >= 8 && strlen($code) <= 14) {
                        $gtin[] = ltrim($code, '0');
                    }
                }
            } elseif (preg_match(self::MODEL_KEYS, (string) $k)) {
                $m = self::normModel($v);
                if ($m !== '') {
                    $models[] = $m;
                }
            }
        }
        foreach (self::modelTokens((string) $p->title) as $m) {
            $models[] = $m;
        }

        return [
            'gtin' => array_values(array_unique($gtin)),
            'models' => array_values(array_unique($models)),
            'sizes' => self::sizes((string) $p->title),
            'title' => self::normTitle((string) $p->title, (string) $p->brand),
        ];
    }

    private function store(string $a, string $b, string $method, float $score): int
    {
        [$a, $b] = strcmp($a, $b) < 0 ? [$a, $b] : [$b, $a];
        $row = ProductMatch::query()->where('product_id', $a)->where('match_id', $b)->first();
        if ($row) {
            if ($row->status === 'pending' && ($row->method !== $method || abs($row->score - $score) > 0.001)) {
                $row->update(['method' => $method, 'score' => $score]);
            }

            return 0;
        }
        ProductMatch::query()->create(['product_id' => $a, 'match_id' => $b, 'method' => $method, 'score' => $score, 'status' => 'pending']);

        return 1;
    }

    /**
     * Ids of the other listings shown for a product: confirmed, or pending with a strong score. Never rejected.
     *
     * @return array<string, array{method: string, score: float, status: string}>
     */
    public function matchesFor(string $productId): array
    {
        $rows = ProductMatch::query()->where(fn ($q) => $q->where('product_id', $productId)->orWhere('match_id', $productId))
            ->where(fn ($q) => $q->where('status', 'confirmed')->orWhere(fn ($w) => $w->where('status', 'pending')->where('score', '>=', self::AUTO_SHOW)))
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $other = $r->product_id === $productId ? $r->match_id : $r->product_id;
            $out[$other] = ['method' => $r->method, 'score' => (float) $r->score, 'status' => $r->status];
        }

        return $out;
    }

    // ---- normalisation helpers (also used by tests) ----

    public static function normBrand(string $b): string
    {
        $b = mb_strtolower(trim($b));
        $b = preg_replace('/^(visit the|brand:)\s+/', '', $b);
        $b = preg_replace('/\s+(store|india|official)$/', '', $b);

        return preg_replace('/[^a-z0-9]/', '', $b);
    }

    public static function normModel(string $m): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $m));
    }

    /** Model-like tokens in a title: letters and digits mixed, 4+ chars (e.g. "WH-1000XM5", "SM-A155F", "RMX3998"). */
    public static function modelTokens(string $title): array
    {
        preg_match_all('/\b(?=[A-Za-z0-9\-]*\d)(?=[A-Za-z0-9\-]*[A-Za-z])[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*\b/', $title, $m);
        $out = [];
        foreach ($m[0] as $t) {
            $n = self::normModel($t);
            // skip sizes/quantities ("128GB", "500ml", "5G", "2024")
            if (strlen($n) < 4 || preg_match('/^\d+(GB|TB|MB|ML|L|G|KG|MAH|W|HZ|MM|CM|INCH|PCS|X)$/', $n) || preg_match('/^\d+G$/', $n)) {
                continue;
            }
            $out[] = $n;
        }

        return array_values(array_unique($out));
    }

    /** Sizes / capacities in a title, normalised ("128gb", "500ml", "1kg"); "4G"/"5G" network names are skipped. */
    public static function sizes(string $title): array
    {
        preg_match_all('/(\d+(?:\.\d+)?)\s?(gb|tb|ml|l|kg|g|mah|w|inch|pcs)\b/i', $title, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $x) {
            $unit = strtolower($x[2]);
            if ($unit === 'g' && in_array($x[1], ['2', '3', '4', '5'], true)) {
                continue;
            }
            $out[] = (str_contains($x[1], '.') ? rtrim(rtrim($x[1], '0'), '.') : $x[1]).$unit;
        }

        return array_values(array_unique($out));
    }

    /** True when both titles state a size in the same unit and none of those sizes agree ("128gb" vs "256gb"). */
    public static function sizesConflict(array $a, array $b): bool
    {
        $unit = fn (string $s) => preg_replace('/^[\d.]+/', '', $s);
        foreach (array_unique(array_map($unit, $a)) as $u) {
            $va = array_filter($a, fn ($s) => $unit($s) === $u);
            $vb = array_filter($b, fn ($s) => $unit($s) === $u);
            if ($vb && ! array_intersect($va, $vb)) {
                return true;
            }
        }

        return false;
    }

    public static function normTitle(string $title, string $brand = ''): string
    {
        $t = mb_strtolower($title);
        $t = preg_replace('/\(.*?\)|\[.*?\]/', ' ', $t);
        $t = preg_replace('/[^a-z0-9 ]+/', ' ', $t);
        $words = array_filter(explode(' ', $t), fn ($w) => $w !== '' && ! in_array($w, self::STOP, true));
        $b = mb_strtolower(trim($brand));
        if ($b !== '') {
            $words = array_filter($words, fn ($w) => $w !== $b);
        }

        return implode(' ', array_slice(array_values($words), 0, 14));
    }

    /** Jaccard similarity of character trigrams (pg_trgm style, padded words). */
    public static function similarity(string $a, string $b): float
    {
        $ta = self::trigrams($a);
        $tb = self::trigrams($b);
        if (! $ta || ! $tb) {
            return 0.0;
        }
        $inter = count(array_intersect_key($ta, $tb));

        return $inter / (count($ta) + count($tb) - $inter);
    }

    /** @return array<string, true> */
    private static function trigrams(string $s): array
    {
        $out = [];
        foreach (explode(' ', $s) as $w) {
            if ($w === '') {
                continue;
            }
            $w = '  '.$w.' ';
            for ($i = 0, $n = strlen($w) - 2; $i < $n; $i++) {
                $out[substr($w, $i, 3)] = true;
            }
        }

        return $out;
    }

    /** Delivery estimate (days) per store; Prime/fast-dispatch listings arrive a day earlier. */
    public static function delivery(Product $p): array
    {
        $base = ['amazon' => [7, 12], 'flipkart' => [8, 13], 'bigbasket' => [6, 10], 'myntra' => [8, 13]][$p->market] ?? [7, 12];
        $fast = $p->prime ? 1 : 0;

        return ['min' => $base[0] - $fast, 'max' => $base[1] - $fast];
    }
}
