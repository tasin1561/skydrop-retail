<?php

namespace App\Sources;

use App\Sources\Exceptions\SourceException;
use App\Sources\Parsers\BigBasketParser;
use App\Sources\Support\OxylabsClient;
use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;

/** bigbasket.com through the Oxylabs universal source (url + render html), parsed from __NEXT_DATA__ / JSON-LD. */
class OxylabsBigBasketSource extends AbstractSource
{
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
        $url = 'https://www.bigbasket.com/ps/?'.http_build_query(['q' => $query, 'page' => $page]);

        return $this->page($url, 'search', "$query#$page");
    }

    public function product(string $id): SourceProduct
    {
        $r = $this->client->query(['source' => 'universal', 'url' => 'https://www.bigbasket.com/pd/'.preg_replace('/^bb/i', '', $id).'/', 'render' => 'html'],
            'product', 'bigbasket', $id, $this->hedge);
        $html = (string) $r['content'];
        $data = $this->parser->product($html, $id);
        if ($data['title'] === '') {
            throw new SourceException("Unparseable BigBasket page for $id");
        }

        return new SourceProduct($data, $this->name(), false, $html);
    }

    public function bestsellers(string $categoryId, int $page = 1): SearchPage
    {
        return $this->search($categoryId, $page);
    }

    public function deals(int $page = 1): SearchPage
    {
        return $this->page((string) config('retail.offers.deal_urls.bigbasket', 'https://www.bigbasket.com/offers/'), 'deals', 'deals#'.$page);
    }

    private function page(string $url, string $type, string $key): SearchPage
    {
        $r = $this->client->query(['source' => 'universal', 'url' => $url, 'render' => 'html'], $type, 'bigbasket', $key, $this->hedge);

        return $this->parser->listing((string) $r['content'])->withProvider($this->name(), false);
    }
}
