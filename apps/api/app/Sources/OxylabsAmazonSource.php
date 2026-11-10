<?php

namespace App\Sources;

use App\Sources\Exceptions\SourceException;
use App\Sources\Parsers\AmazonHtmlParser;
use App\Sources\Parsers\AmazonListingMapper;
use App\Sources\Support\OxylabsClient;
use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;
use Throwable;

/**
 * amazon.in through Oxylabs. Product pages are fetched RAW (no parse flag) with the Kolkata pincode and parsed by
 * our own AmazonHtmlParser; search and bestsellers use parse:true (same price per result).
 */
class OxylabsAmazonSource extends AbstractSource
{
    /** Our sort keys → Oxylabs amazon_search context sort_by (Oxylabs rejects Amazon's own "price-asc-rank" names with HTTP 400). */
    private const SORTS = ['price_asc' => 'price_low_to_high', 'price_desc' => 'price_high_to_low', 'rating' => 'average_review',
        'newest' => 'most_recent', 'relevance' => null, 'discount' => null];

    public function __construct(private OxylabsClient $client, private AmazonHtmlParser $parser, private AmazonListingMapper $mapper) {}

    public function name(): string
    {
        return 'oxylabs';
    }

    public function market(): string
    {
        return 'amazon';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function supportsPincode(): bool
    {
        return true;
    }

    public function isMetered(): bool
    {
        return true;
    }

    public function search(string $query, int $page = 1, string $sort = 'relevance', ?string $category = null): SearchPage
    {
        $payload = array_filter([
            'source' => 'amazon_search', 'domain' => 'in', 'query' => $query, 'start_page' => $page > 1 ? $page : null,
            'geo_location' => $this->pincode(), 'parse' => true,
            'context' => ($s = self::SORTS[$sort] ?? null) ? [['key' => 'sort_by', 'value' => $s]] : null,
        ], fn ($v) => $v !== null);
        $r = $this->client->query($payload, 'search', 'amazon', "$query#$page", $this->hedge);

        return $this->mapper->search((array) $r['content'])->withProvider($this->name(), true);
    }

    public function product(string $id): SourceProduct
    {
        $r = $this->client->query($this->productPayload($id), 'product', 'amazon', $id, $this->hedge);

        return $this->toProduct($id, $r);
    }

    public function products(array $ids): array
    {
        $payloads = [];
        foreach ($ids as $id) {
            $payloads[$id] = $this->productPayload($id);
        }
        $out = [];
        foreach ($this->client->pool($payloads, 'product', 'amazon') as $id => $r) {
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
        $r = $this->client->query(array_filter([
            'source' => 'amazon_bestsellers', 'domain' => 'in', 'query' => $categoryId, 'start_page' => $page > 1 ? $page : null,
            'geo_location' => $this->pincode(), 'parse' => true,
        ]), 'bestsellers', 'amazon', "$categoryId#$page");

        return $this->mapper->bestsellers((array) $r['content'])->withProvider($this->name(), true);
    }

    /**
     * Today's Deals: collect ASINs from the deals page (or the sale event it redirects to); details come from product
     * fetches. Fetched with the "amazon" source: Oxylabs refuses amazon.in URLs on "universal", and the ASINs are in
     * the HTML without rendering.
     */
    public function deals(int $page = 1): SearchPage
    {
        $r = $this->client->query([
            'source' => 'amazon', 'url' => (string) config('retail.offers.deal_urls.amazon', 'https://www.amazon.in/deals'),
            'geo_location' => $this->pincode(),
        ], 'deals', 'amazon', 'deals#'.$page);
        preg_match_all('#/dp/([A-Z0-9]{10})|data-asin="([A-Z0-9]{10})"#', (string) $r['content'], $m);
        $ids = array_values(array_unique(array_filter(array_merge($m[1], $m[2]))));

        return (new SearchPage(array_map(fn ($id) => ['id' => $id, 'market' => 'amazon', 'stub' => true], $ids), count($ids)))
            ->withProvider($this->name(), true);
    }

    /** @return array<string, mixed> */
    private function productPayload(string $id): array
    {
        return ['source' => 'amazon_product', 'domain' => 'in', 'query' => $id, 'geo_location' => $this->pincode()];
    }

    /** @param array<string, mixed> $r */
    private function toProduct(string $id, array $r): SourceProduct
    {
        $html = $r['content'] ?? null;
        if (! is_string($html) || strlen($html) < 1000) {
            throw new SourceException("Empty product page for $id");
        }
        $data = $this->parser->parse($html, $id);
        if ($data['title'] === '') {
            throw new SourceException("Unparseable product page for $id (captcha or layout change)");
        }

        return new SourceProduct($data, $this->name(), true, $html);
    }

    private function pincode(): string
    {
        return (string) config('retail.pincode', '700001');
    }
}
