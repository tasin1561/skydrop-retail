<?php

namespace App\Sources;

use App\Models\Product;
use App\Services\Categories\CategoryMatcher;
use App\Services\Categories\CategoryTree;
use App\Sources\Exceptions\SourceException;
use App\Sources\Parsers\BigBasketParser;
use App\Sources\Support\Offers;
use App\Sources\Support\OxylabsClient;
use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;
use Illuminate\Support\Str;
use Throwable;

/**
 * bigbasket.com through the Oxylabs universal source (live-tested Nov 2026). BigBasket blocks most automated requests,
 * and its desktop listing pages no longer carry products, so:
 * - listings (search, categories, deals) come from the MOBILE site, which still builds category (/pc/{slug}/,
 *   /cl/{slug}/) and brand (/pb/{slug}/) pages on the server (4-8 s, fast and reliable in tests). There is no search
 *   page with products: a search tries the category and brand pages named after the words, then the category page Google
 *   finds for "bigbasket <words>", then the BigBasket top-level category our tree maps the words to (keeping the
 *   products whose names match).
 * - product pages: the desktop page (full data: price, MRP, stock, pack sizes, breadcrumb) comes back for about half of
 *   the requests made as a tablet; a plain desktop request mostly fails ("613" after 20-30 s). When only the mobile page
 *   comes back, its title still has the live price: the stored details are kept with that price.
 * All unrendered ($0.90 per 1,000).
 */
class OxylabsBigBasketSource extends AbstractSource
{
    private const BASE = 'https://www.bigbasket.com';

    public function __construct(private OxylabsClient $client, private BigBasketParser $parser) {}

    public function name(): string
    {
        return 'oxylabs';
    }

    public function market(): string
    {
        return 'bigbasket';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function supportsPincode(): bool
    {
        return false;
    }

    public function isMetered(): bool
    {
        return true;
    }

    public function search(string $query, int $page = 1, string $sort = 'relevance', ?string $category = null): SearchPage
    {
        $slug = Str::slug($query);
        $words = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim($query))) ?: [], fn ($w) => mb_strlen($w) > 1));
        // [path, filter by the words?]: a page named after the words is all relevant; broader pages are filtered.
        $tries = $slug !== '' ? [["/pc/$slug/", false], ["/pb/$slug/", false], ['google', true]] : [];
        if ($top = $this->topCategory($query)) {
            $tries[] = ["/cl/$top/", true];
        }
        for ($i = 0; $i < count($tries); $i++) {
            [$path, $filter] = $tries[$i];
            if ($path === 'google') {
                // Insert the pages Google finds right here, in their order.
                array_splice($tries, $i + 1, 0, array_map(fn ($p) => [$p, true], $this->googleListings($query)));

                continue;
            }
            try {
                $listing = $this->listingPage($path, $page, 'search', "$query#$page#$i");
            } catch (SourceException) {
                continue;
            }
            $items = $filter ? $this->matching($listing->items, $words) : $listing->items;
            if ($items !== []) {
                return (new SearchPage($this->sorted($items, $sort), max(count($items), $listing->total), $listing->pages))->withProvider($this->name(), false);
            }
        }

        return (new SearchPage([]))->withProvider($this->name(), false);
    }

    public function product(string $id): SourceProduct
    {
        $url = self::BASE.'/pd/'.preg_replace('/^bb/i', '', $id).'/';
        $mobile = null;
        $last = null;
        // Tablet first (the full desktop page about half the time), then mobile (always quick, title price only).
        // A plain desktop request mostly ends in "613" after 20-30 s, so it isn't tried.
        foreach ([['user_agent_type' => 'tablet'], ['user_agent_type' => 'mobile']] as $extra) {
            try {
                $r = $this->client->query(['source' => 'universal', 'url' => $url] + $extra, 'product', 'bigbasket', $id);
            } catch (Throwable $e) {
                $last = $e;

                continue;
            }
            $html = (string) ($r['content'] ?? '');
            $data = $this->parser->product($html, $id);
            if ($data['title'] !== '' && $data['price_inr'] > 0) {
                return new SourceProduct($data, $this->name(), false, $html);
            }
            $mobile ??= BigBasketParser::mobileTitle($html);
        }
        if ($mobile) {
            return new SourceProduct($this->fromMobile($id, $mobile), $this->name(), false);
        }

        throw $last instanceof SourceException ? $last : new SourceException("BigBasket page for $id did not come back", 0, $last);
    }

    public function bestsellers(string $categoryId, int $page = 1): SearchPage
    {
        // A BigBasket listing path ("/pc/…/", "/cl/…/", "/pb/…/") is fetched as is; anything else is a search.
        if (preg_match('#^/?(pc|cl|pb)/#', $categoryId)) {
            return $this->listingPage('/'.ltrim($categoryId, '/'), $page, 'bestsellers', md5($categoryId)."#$page");
        }

        return $this->search($categoryId, $page);
    }

    public function deals(int $page = 1): SearchPage
    {
        $urls = array_values(array_filter((array) config('retail.offers.deal_urls.bigbasket', [])));
        if ($urls === []) {
            return new SearchPage([]);
        }
        // One top-level category per run, rotating; real discounts only (at least 10% below MRP).
        $slot = intdiv(time(), 3600 * max(1, (int) config('retail.offers.ingest_every_hours', 6)));
        $path = (string) $urls[($slot + $page - 1) % count($urls)];
        $listing = $this->listingPage($path, 1, 'deals', md5($path));
        $deals = array_values(array_filter($listing->items, fn ($r) => (float) $r['mrp_inr'] > 0 && (float) $r['price_inr'] <= 0.9 * (float) $r['mrp_inr']));
        usort($deals, fn ($a, $b) => ($a['price_inr'] / $a['mrp_inr']) <=> ($b['price_inr'] / $b['mrp_inr']));

        return (new SearchPage($deals, count($deals), 1))->withProvider($this->name(), false);
    }

    private function listingPage(string $path, int $page, string $type, string $key): SearchPage
    {
        $url = str_starts_with($path, 'http') ? $path : self::BASE.$path;
        if ($page > 1) {
            $url .= (str_contains($url, '?') ? '&' : '?').'page='.$page;
        }
        $r = $this->client->query(['source' => 'universal', 'url' => $url, 'user_agent_type' => 'mobile'], $type, 'bigbasket', $key);
        $listing = $this->parser->listing((string) ($r['content'] ?? ''));
        if ($listing->items === []) {
            throw new SourceException('BigBasket listing without products: '.$url);
        }
        // About 20 products per page on the mobile site; a full page means there is probably a next one.
        $pages = count($listing->items) >= 20 ? $page + 1 : $page;

        return (new SearchPage($listing->items, $listing->total, $pages))->withProvider($this->name(), false);
    }

    /**
     * BigBasket's own category or brand pages for the words, found through Google ("bigbasket jaggery" →
     * /pc/foodgrains-oil-masala/salt-sugar-jaggery/sugar-jaggery/). Short pages (/pc/{words}/) exist only for some
     * categories, and Google also lists retired paths, so the first three are tried.
     *
     * @return list<string>
     */
    private function googleListings(string $query): array
    {
        try {
            $r = $this->client->query(['source' => 'google_search', 'query' => 'bigbasket '.$query, 'domain' => 'co.in', 'parse' => true],
                'search', 'bigbasket', "$query#google");
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ((array) ($r['content']['results']['organic'] ?? []) as $o) {
            $url = (string) ($o['url'] ?? '');
            $path = (string) parse_url($url, PHP_URL_PATH);
            if (str_contains($url, 'bigbasket.com/') && preg_match('#^/(pc|pb|cl)/[a-z0-9/-]+/?$#', $path)) {
                $out[] = rtrim($path, '/').'/';
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 3);
    }

    /** The BigBasket top-level category (cl slug) our tree maps the words to, e.g. "basmati" → foodgrains-oil-masala. */
    private function topCategory(string $query): ?string
    {
        try {
            $node = app(CategoryMatcher::class)->match('bigbasket', $query);
            $tree = app(CategoryTree::class);
            for ($n = $node ? $tree->find($node['id']) : null; $n; $n = $n['parent_id'] ? $tree->find($n['parent_id']) : null) {
                if (! empty($n['sources']['bigbasket']['slug'])) {
                    return (string) $n['sources']['bigbasket']['slug'];
                }
            }
        } catch (Throwable) {
            // no tree (fresh install): category pages named after the words only
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $words
     * @return list<array<string, mixed>>
     */
    private function matching(array $items, array $words): array
    {
        if ($words === []) {
            return $items;
        }
        $scored = [];
        foreach ($items as $i => $row) {
            $hay = mb_strtolower($row['title'].' '.($row['brand'] ?? ''));
            $hits = count(array_filter($words, fn ($w) => str_contains($hay, rtrim($w, 's'))));
            if ($hits > 0) {
                $scored[] = [$hits, $i, $row];
            }
        }
        usort($scored, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_column($scored, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sorted(array $items, string $sort): array
    {
        match ($sort) {
            'price_asc' => usort($items, fn ($a, $b) => $a['price_inr'] <=> $b['price_inr']),
            'price_desc' => usort($items, fn ($a, $b) => $b['price_inr'] <=> $a['price_inr']),
            'discount' => usort($items, fn ($a, $b) => ($a['price_inr'] / max(1, $a['mrp_inr'])) <=> ($b['price_inr'] / max(1, $b['mrp_inr']))),
            'rating' => usort($items, fn ($a, $b) => $b['rating'] <=> $a['rating']),
            default => null,
        };

        return $items;
    }

    /**
     * Only the mobile page came back: keep what we stored (photos, MRP, packs, description) with the live price from
     * its title. Stock isn't on that page; it is confirmed when we buy.
     *
     * @param  array{title: string, price: float}  $m
     * @return array<string, mixed>
     */
    private function fromMobile(string $id, array $m): array
    {
        $p = Product::query()->with('details')->find($id);
        $d = $p?->details;
        $images = (array) ($d->images ?? []) ?: array_values(array_filter([$p?->image]));

        return [
            'id' => $id, 'market' => 'bigbasket',
            'title' => $p?->title ?: $m['title'],
            'brand' => $p?->brand,
            'price_inr' => $m['price'],
            'mrp_inr' => max((float) ($p?->mrp_inr ?? 0), $m['price']),
            'rating' => (float) ($p?->rating ?? 0), 'reviews' => (int) ($p?->reviews ?? 0),
            'image' => $images[0] ?? null, 'images' => $images,
            'prime' => false, 'best_seller' => false, 'amazons_choice' => false, 'sales_volume' => '',
            'in_stock' => true, 'deliverable' => true,
            'stock_text' => 'Stock is confirmed when we buy', 'delivery_text' => null,
            'bullets' => (array) ($d->bullets ?? []), 'specs' => (array) ($d->specs ?? []),
            'weight' => $d->weight ?? null, 'variants' => (array) ($d->variants ?? []),
            'videos' => [], 'has_videos' => false, 'aplus' => [], 'review_list' => [], 'star_breakdown' => [],
            'category_path' => (array) ($d->category_path ?? []),
            'offers' => $p && (float) $p->mrp_inr > $m['price'] ? [Offers::make('deal', round(100 * (1 - $m['price'] / (float) $p->mrp_inr)).'% OFF', null, (float) $p->mrp_inr - $m['price'])] : [],
            'deal_badge' => null, 'deal_ends_at' => null, 'coupon_inr' => null, 'effective_price_inr' => null,
        ];
    }
}
