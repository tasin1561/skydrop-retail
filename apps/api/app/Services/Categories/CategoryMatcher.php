<?php

namespace App\Services\Categories;

/**
 * Maps a product onto the deepest matching node of the unified tree from store breadcrumbs (Amazon wayfinding,
 * Flipkart/BigBasket crumbs) and/or title keywords.
 *
 * Every node scores its own keyword hits (keywords = curated aliases + name parts, with singular/plural variants;
 * a keyword counts once whichever variant hits):
 *  - breadcrumb hit: 2 x words in the keyword + 0.5 per crumb level (deeper crumbs are more specific),
 *    +3 when a crumb equals the node name;
 *  - title hit: words in the keyword, x1 in the title head (before the first "|", "(", ",", " - ", " with ", " for "),
 *    x0.4 in the tail (ingredients, compatibility: "Lipstick with Vitamin E", "Cover for OnePlus 5G");
 *    title hits on nodes that have children count half (gender/department words are context, not the product).
 * A candidate's score is the sum of its own score and its ancestors' (a consistent chain wins), it must have hits of
 * its own, and only nodes the product's store sells are considered. Ties go to the deeper node, then tree order.
 * The web demo mirrors this in apps/web/src/lib/categoryMatch.ts.
 */
class CategoryMatcher
{
    /** @var array<int, list<array{0: list<string>, 1: int}>>|null node id => [[variants, words]] */
    private ?array $index = null;

    private int $indexedFor = -1;

    public function __construct(private CategoryTree $tree) {}

    public static function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = str_replace(['’', '`'], "'", $s);
        $s = preg_replace("/'s\b/u", 's', $s) ?? $s;
        $s = str_replace("'", '', $s);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/(?<=\d)(?=[a-z])|(?<=[a-z])(?=\d)/', ' ', $s) ?? $s; // 8000mah -> 8000 mah

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    /** @return list<string> the keyword plus its singular/plural variant (last word only) */
    public static function variants(string $kw): array
    {
        $out = [$kw];
        if (strlen($kw) < 3 || preg_match('/\d$/', $kw)) {
            return $out;
        }
        if (str_ends_with($kw, 'ies')) {
            $out[] = substr($kw, 0, -3).'y';
        } elseif (str_ends_with($kw, 'ses') || str_ends_with($kw, 'xes') || str_ends_with($kw, 'ches') || str_ends_with($kw, 'shes')) {
            $out[] = substr($kw, 0, -2);
        } elseif (str_ends_with($kw, 's') && ! str_ends_with($kw, 'ss') && strlen($kw) > 3) {
            $out[] = substr($kw, 0, -1);
        } elseif (str_ends_with($kw, 'y') && ! preg_match('/[aeiou]y$/', $kw)) {
            $out[] = substr($kw, 0, -1).'ies';
        } else {
            $out[] = $kw.'s';
        }

        return $out;
    }

    /**
     * Title head (the product noun) and tail (variants, ingredients, compatibility).
     *
     * @return array{0: string, 1: string}
     */
    public static function splitTitle(string $title): array
    {
        if (preg_match('/\s*(?:\||\(|\[|,|:|\s[-–—]\s|\swith\s|\sfor\s|\sw\/\s)/iu', $title, $m, PREG_OFFSET_CAPTURE) && $m[0][1] > 0) {
            return [substr($title, 0, $m[0][1]), substr($title, $m[0][1])];
        }

        return [$title, ''];
    }

    /** @return array<int, list<array{0: list<string>, 1: int}>> */
    private function index(): array
    {
        $nodes = $this->tree->nodes();
        if ($this->index !== null && $this->indexedFor === count($nodes)) {
            return $this->index;
        }
        $this->index = [];
        foreach ($nodes as $id => $n) {
            $kws = [];
            $seen = [];
            foreach (array_merge([$n['name']], $n['keywords']) as $k) {
                $k = self::normalize((string) $k);
                if ($k === '' || isset($seen[$k])) {
                    continue;
                }
                $vs = array_values(array_filter(self::variants($k), fn ($v) => ! isset($seen[$v])));
                foreach ($vs as $v) {
                    $seen[$v] = true;
                }
                $kws[] = [array_map(fn ($v) => ' '.$v.' ', $vs), substr_count($k, ' ') + 1];
            }
            $this->index[$id] = $kws;
        }
        $this->indexedFor = count($nodes);

        return $this->index;
    }

    /**
     * @param  list<string>  $crumbs  store breadcrumbs, root first
     * @param  string|null  $prior  current pricing group (small tie-breaker towards it)
     * @return array{id: int, path: string, pricing_group: string, score: float}|null
     */
    public function match(?string $market, string $title, array $crumbs = [], ?string $prior = null): ?array
    {
        $nodes = $this->tree->nodes();
        if (! $nodes) {
            return null;
        }
        $res = $this->best($nodes, $market, $title, $crumbs, $prior);
        if (! $res && $market) {
            $res = $this->best($nodes, null, $title, $crumbs, $prior); // store not mapped for that node: still categorise
        }

        return $res;
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function best(array $nodes, ?string $market, string $title, array $crumbs, ?string $prior): ?array
    {
        $index = $this->index();
        [$head, $tail] = self::splitTitle($title);
        $h = ' '.self::normalize($head).' ';
        $t = ' '.self::normalize($tail).' ';
        $cs = [];
        foreach (array_values($crumbs) as $i => $c) {
            $n = self::normalize((string) $c);
            if ($n !== '') {
                $cs[] = [' '.$n.' ', $i, $n];
            }
        }

        $own = [];
        foreach ($nodes as $id => $n) {
            if ($market && ! in_array($market, $n['stores'], true)) {
                continue;
            }
            $s = 0.0;
            $name = self::normalize($n['name']);
            $titleFactor = $n['children'] ? 0.5 : 1.0;
            foreach ($index[$id] ?? [] as [$needles, $words]) {
                $crumb = $inTitle = 0.0;
                foreach ($needles as $needle) {
                    foreach ($cs as [$hay, $i]) {
                        if (str_contains($hay, $needle)) {
                            $crumb = max($crumb, 2 * $words + 0.5 * $i);
                        }
                    }
                    if (str_contains($h, $needle)) {
                        $inTitle = max($inTitle, $words);
                    } elseif (str_contains($t, $needle)) {
                        $inTitle = max($inTitle, 0.4 * $words);
                    }
                }
                $s += $crumb + $titleFactor * $inTitle;
            }
            foreach ($cs as [, $i, $plain]) {
                if ($plain === $name) {
                    $s += 3 + 0.5 * $i;
                }
            }
            if ($s > 0) {
                $own[$id] = $s;
            }
        }

        $best = null;
        foreach ($own as $id => $s) {
            $total = 0.0;
            for ($cur = $nodes[$id]; $cur; $cur = $cur['parent_id'] ? ($nodes[$cur['parent_id']] ?? null) : null) {
                $total += $own[$cur['id']] ?? 0;
            }
            if ($prior && $nodes[$id]['pricing_group'] === $prior) {
                $total += 0.5;
            }
            $cand = ['id' => $id, 'path' => $nodes[$id]['path'], 'pricing_group' => $nodes[$id]['pricing_group'], 'score' => $total, 'depth' => $nodes[$id]['depth']];
            if (! $best || $total > $best['score'] + 1e-9 || (abs($total - $best['score']) < 1e-9 && $cand['depth'] > $best['depth'])) {
                $best = $cand;
            }
        }
        if (! $best || $best['score'] < 0.8) {
            return null;
        }
        unset($best['depth']);

        return $best;
    }
}
