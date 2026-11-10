<?php

namespace App\Sources;

use App\Sources\Exceptions\SourceException;
use App\Sources\Parsers\FlipkartHtmlParser;
use App\Sources\Support\OxylabsClient;
use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;
use Throwable;

/**
 * flipkart.com through Oxylabs.
 *
 * Every page is fetched without JS rendering: Flipkart sends the full product data (JSON-LD + page state) and the
 * search cards in the HTML, rendering costs more per result, and rendered jobs are rate-limited by Oxylabs.
 * - search: flipkart_search for relevance, the search URL with &sort= for other orders
 * - product: the product URL, with ?pid= for an exact variant ("itm…-pid" ids)
 * - deals: one discounted category listing per run, rotating through retail.offers.deal_urls.flipkart
 */
class OxylabsFlipkartSource extends AbstractSource
{
    /** Our sort keys → Flipkart's &sort= values (no discount sort on Flipkart). */
    private const SORTS = ['price_asc' => 'price_asc', 'price_desc' => 'price_desc', 'rating' => 'popularity', 'newest' => 'recency_desc'];

    public function __construct(private OxylabsClient $client, private FlipkartHtmlParser $parser) {}

    public function name(): string
    {
        return 'oxylabs';
    }

    public function market(): string
    {
        return 'flipkart';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function supportsPincode(): bool
    {
        return false; // Flipkart target has no geo_location: delivery is confirmed at purchase time.
    }

    public function isMetered(): bool
    {
        return true;
    }

    public function search(string $query, int $page = 1, string $sort = 'relevance', ?string $category = null): SearchPage
    {
        $flipkartSort = self::SORTS[$sort] ?? null;
        $payload = $flipkartSort
            ? ['source' => 'flipkart', 'url' => 'https://www.flipkart.com/search?'.http_build_query(array_filter(
                ['q' => $query, 'sort' => $flipkartSort, 'page' => $page > 1 ? $page : null]))]
            : array_filter(['source' => 'flipkart_search', 'query' => $query, 'start_page' => $page > 1 ? $page : null]);
        $r = $this->client->query($payload, 'search', 'flipkart', "$query#$page".($flipkartSort ? "#$flipkartSort" : ''), $this->hedge);

        return $this->parser->listing((string) $r['content'])->withProvider($this->name(), false);
    }

    public function product(string $id): SourceProduct
    {
        $r = $this->client->query($this->productPayload($id), 'product', 'flipkart', $id, $this->hedge);

        return $this->toProduct($id, $r);
    }

    /** Several products at once (variant siblings, deals): one concurrent pool instead of one call after another. */
    public function products(array $ids): array
    {
        $payloads = [];
        foreach ($ids as $id) {
            $payloads[$id] = $this->productPayload((string) $id);
        }
        $out = [];
        foreach ($this->client->pool($payloads, 'product', 'flipkart') as $id => $r) {
            try {
                $out[$id] = $r instanceof Throwable ? $r : $this->toProduct((string) $id, $r);
            } catch (Throwable $e) {
                $out[$id] = $e;
            }
        }

        return $out;
    }

    public function bestsellers(string $categoryId, int $page = 1): SearchPage
    {
        // A Flipkart listing URL (…/pr?sid=…) is fetched as is (most popular first); anything else is a search.
        if (str_contains($categoryId, 'flipkart.com/') || str_starts_with($categoryId, '/')) {
            return $this->listingPage($categoryId, $page, 'bestsellers', 'popularity');
        }

        return $this->search($categoryId, $page, 'rating');
    }

    public function deals(int $page = 1): SearchPage
    {
        $urls = array_values(array_filter((array) config('retail.offers.deal_urls.flipkart', [])));
        if ($urls === []) {
            return new SearchPage([]);
        }
        // One category per run, rotating (runs are every few hours), so each run costs one listing call.
        $slot = intdiv(time(), 3600 * max(1, (int) config('retail.offers.ingest_every_hours', 6)));

        $listing = $this->listingPage((string) $urls[($slot + $page - 1) % count($urls)], 1, 'deals', null);
        // Sponsored cards ignore the discount filter: keep real deals only (at least 10% below MRP).
        $deals = array_values(array_filter($listing->items, fn ($r) => (float) $r['mrp_inr'] > 0 && (float) $r['price_inr'] <= 0.9 * (float) $r['mrp_inr']));

        return (new SearchPage($deals, count($deals), 1))->withProvider($this->name(), false);
    }

    private function listingPage(string $url, int $page, string $type, ?string $sort): SearchPage
    {
        // Keep the URL as written (Flipkart filters are repeated "p[]=" params that a re-encode would turn into p[0]=).
        $full = str_starts_with($url, '/') ? 'https://www.flipkart.com'.$url : $url;
        $full = (string) preg_replace('/([?&])page=\d+(&|$)/', '$1', $full);
        if ($sort && ! preg_match('/[?&]sort=/', $full)) {
            $full .= (str_contains($full, '?') ? '&' : '?').'sort='.$sort;
        }
        if ($page > 1) {
            $full .= (str_contains($full, '?') ? '&' : '?').'page='.$page;
        }
        $full = rtrim($full, '&?');
        $r = $this->client->query(['source' => 'flipkart', 'url' => $full], $type, 'flipkart', md5($full));

        return $this->parser->listing((string) $r['content'])->withProvider($this->name(), false);
    }

    /** @return array<string, mixed> */
    private function productPayload(string $id): array
    {
        return ['source' => 'flipkart', 'url' => FlipkartHtmlParser::productUrl($id)];
    }

    private function toProduct(string $id, array $r): SourceProduct
    {
        $html = (string) ($r['content'] ?? '');
        if (strlen($html) < 1000) {
            throw new SourceException("Empty Flipkart page for $id");
        }
        $data = $this->parser->product($html, $id);
        if ($data['title'] === '') {
            throw new SourceException("Unparseable Flipkart page for $id");
        }

        return new SourceProduct($data, $this->name(), false, $html);
    }
}
