<?php

namespace App\Sources;

use App\Sources\Exceptions\ProductGoneException;
use App\Sources\Exceptions\SourceException;
use App\Sources\Parsers\MyntraParser;
use App\Sources\Support\OxylabsClient;
use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;
use Illuminate\Support\Str;
use Throwable;

/**
 * myntra.com through the Oxylabs universal source (live-tested Nov 2026).
 * - search / category / deals: listing pages (full product cards in window.__myx). Tried WITHOUT rendering first
 *   (about 2 s, cheapest rate); Myntra often answers plain requests with a 483-byte "Site Maintenance" block page,
 *   and then the page is fetched once more WITH rendering (10-30 s, which has always come back complete).
 * - product: https://www.myntra.com/{styleId} WITH rendering (plain fetches are refused with Oxylabs 613); a block
 *   page is retried once.
 * - Oxylabs allows only a few rendered jobs at once for the account, so colour siblings go 2 at a time.
 */
class OxylabsMyntraSource extends AbstractSource
{
    /** Our sort keys → Myntra's &sort= values. */
    private const SORTS = ['price_asc' => 'price_asc', 'price_desc' => 'price_desc', 'rating' => 'Customer Rating',
        'newest' => 'new', 'discount' => 'discount', 'popular' => 'popularity'];

    /** Rendered jobs at once for colour siblings (Oxylabs answers more with "Too many requests (Total Render Dynamic)"). */
    private const RENDER_CONCURRENCY = 2;

    public function __construct(private OxylabsClient $client, private MyntraParser $parser) {}

    public function name(): string
    {
        return 'oxylabs';
    }

    public function market(): string
    {
        return 'myntra';
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
        // Myntra's search page: /<words-with-dashes>?rawQuery=<words>
        $slug = Str::slug($query) ?: 'search';
        $params = array_filter(['rawQuery' => $query, 'sort' => self::SORTS[$sort] ?? null, 'p' => $page > 1 ? $page : null]);
        $url = 'https://www.myntra.com/'.$slug.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $this->listingPage($url, 'search', "$query#$page#$sort");
    }

    public function product(string $id): SourceProduct
    {
        // No hedging: a duplicate rendered job would use up the account's small render allowance.
        $r = $this->client->query($this->productPayload($id), 'product', 'myntra', $id);
        if (MyntraParser::state((string) ($r['content'] ?? '')) === null) {
            $r = $this->client->query($this->productPayload($id), 'product', 'myntra', $id); // block page: once more
        }

        return $this->toProduct($id, $r);
    }

    /** Colour siblings concurrently (rendered jobs, so the client's concurrency limit applies). */
    public function products(array $ids): array
    {
        $payloads = [];
        foreach ($ids as $id) {
            $payloads[$id] = $this->productPayload((string) $id);
        }
        $out = [];
        foreach ($this->client->pool($payloads, 'product', 'myntra', self::RENDER_CONCURRENCY) as $id => $r) {
            try {
                if (! $r instanceof Throwable && MyntraParser::state((string) ($r['content'] ?? '')) === null) {
                    $r = $this->client->query($payloads[$id], 'product', 'myntra', (string) $id); // block page: once more
                }
                $out[$id] = $r instanceof Throwable ? $r : $this->toProduct((string) $id, $r);
            } catch (Throwable $e) {
                $out[$id] = $e;
            }
        }

        return $out;
    }

    public function bestsellers(string $categoryId, int $page = 1): SearchPage
    {
        // A Myntra listing path ("men-tshirts") or URL is fetched as is, most popular first; anything else is a search.
        if (str_contains($categoryId, 'myntra.com/') || preg_match('#^/?[a-z0-9-]+$#', $categoryId) && str_contains($categoryId, '-')) {
            $url = str_contains($categoryId, 'myntra.com/') ? $categoryId : 'https://www.myntra.com/'.ltrim($categoryId, '/');

            return $this->listingPage($this->withParams($url, ['sort' => 'popularity', 'p' => $page > 1 ? $page : null]), 'bestsellers', md5($url)."#$page");
        }

        return $this->search($categoryId, $page, 'popular');
    }

    public function deals(int $page = 1): SearchPage
    {
        $urls = array_values(array_filter((array) config('retail.offers.deal_urls.myntra', [])));
        if ($urls === []) {
            return new SearchPage([]);
        }
        // One category per run, rotating, so each run costs one listing call.
        $slot = intdiv(time(), 3600 * max(1, (int) config('retail.offers.ingest_every_hours', 6)));
        $url = (string) $urls[($slot + $page - 1) % count($urls)];
        $listing = $this->listingPage($url, 'deals', md5($url));
        $deals = array_values(array_filter($listing->items, fn ($r) => (float) $r['mrp_inr'] > 0 && (float) $r['price_inr'] <= 0.9 * (float) $r['mrp_inr']));

        return (new SearchPage($deals, count($deals), 1))->withProvider($this->name(), false);
    }

    private function listingPage(string $url, string $type, string $key): SearchPage
    {
        $r = $this->client->query(['source' => 'universal', 'url' => $url], $type, 'myntra', $key);
        $html = (string) ($r['content'] ?? '');
        if (MyntraParser::state($html) === null) {
            // "Site Maintenance" block page: the rendered fetch gets through.
            $r = $this->client->query(['source' => 'universal', 'url' => $url, 'render' => 'html'], $type, 'myntra', $key.'#r');
            $html = (string) ($r['content'] ?? '');
        }
        if (MyntraParser::state($html) === null) {
            throw new SourceException('Myntra listing without page data: '.$url);
        }

        return $this->parser->listing($html)->withProvider($this->name(), false);
    }

    /** @param array<string, mixed> $params */
    private function withParams(string $url, array $params): string
    {
        $url = (string) preg_replace('/([?&])(p|sort)=[^&]*(&|$)/', '$1', $url);
        $url = rtrim($url, '&?');
        $q = http_build_query(array_filter($params, fn ($v) => $v !== null), '', '&', PHP_QUERY_RFC3986);

        return $q === '' ? $url : $url.(str_contains($url, '?') ? '&' : '?').$q;
    }

    /** @return array<string, mixed> */
    private function productPayload(string $id): array
    {
        return ['source' => 'universal', 'url' => MyntraParser::productUrl($id), 'render' => 'html'];
    }

    private function toProduct(string $id, array $r): SourceProduct
    {
        $html = (string) ($r['content'] ?? '');
        $state = MyntraParser::state($html);
        if ($state === null) {
            throw new SourceException("Myntra page without data for $id");
        }
        if (empty($state['pdpData']['id'])) {
            // Myntra serves a "page not found" shell (no pdpData) for removed styles.
            throw new ProductGoneException("myntra $id not found");
        }
        $data = $this->parser->product($html, $id);
        if ($data['title'] === '') {
            throw new SourceException("Unparseable Myntra page for $id");
        }

        return new SourceProduct($data, $this->name(), false, $html);
    }
}
