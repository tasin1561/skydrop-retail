<?php

namespace App\Sources;

use App\Sources\Exceptions\SourceException;
use App\Sources\Parsers\FlipkartHtmlParser;
use App\Sources\Support\OxylabsClient;
use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;

/** flipkart.com through Oxylabs: flipkart_search (no JS) and flipkart_product (render html), parsed best-effort. */
class OxylabsFlipkartSource extends AbstractSource
{
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
        $r = $this->client->query(array_filter(['source' => 'flipkart_search', 'query' => $query, 'start_page' => $page > 1 ? $page : null]),
            'search', 'flipkart', "$query#$page", $this->hedge);

        return $this->parser->listing((string) $r['content'])->withProvider($this->name(), false);
    }

    public function product(string $id): SourceProduct
    {
        // "itm…-pid" opens that exact variant (colour/storage); a bare "itm…" opens Flipkart's default variant.
        [$itm, $pid] = FlipkartHtmlParser::splitId($id);
        $payload = $pid
            ? ['source' => 'flipkart', 'url' => FlipkartHtmlParser::productUrl($id), 'render' => 'html']
            : ['source' => 'flipkart_product', 'product_id' => preg_replace('/^itm/i', '', $itm), 'render' => 'html'];
        $r = $this->client->query($payload, 'product', 'flipkart', $id, $this->hedge);
        $html = (string) $r['content'];
        $data = $this->parser->product($html, $id);
        if ($data['title'] === '') {
            throw new SourceException("Unparseable Flipkart page for $id");
        }

        return new SourceProduct($data, $this->name(), false, $html);
    }

    public function bestsellers(string $categoryId, int $page = 1): SearchPage
    {
        return $this->search($categoryId, $page);
    }

    public function deals(int $page = 1): SearchPage
    {
        $r = $this->client->query(['url' => (string) config('retail.offers.deal_urls.flipkart', 'https://www.flipkart.com/offers-store'), 'render' => 'html'],
            'deals', 'flipkart', 'deals#'.$page);

        return $this->parser->listing((string) $r['content'])->withProvider($this->name(), false);
    }
}
