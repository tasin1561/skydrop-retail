<?php

namespace App\Sources;

use App\Sources\Exceptions\SourceException;
use App\Sources\Support\HttpJsonSource;
use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** OpenWebNinja "Real-Time Amazon Data" on RapidAPI (country IN, no pincode). Last-resort Amazon fallback. */
class OpenWebNinjaAmazonSource extends AbstractSource
{
    use HttpJsonSource;

    private const SORTS = ['price_asc' => 'LOWEST_PRICE', 'price_desc' => 'HIGHEST_PRICE', 'rating' => 'REVIEWS'];

    public function name(): string
    {
        return 'openwebninja';
    }

    public function market(): string
    {
        return 'amazon';
    }

    public function isConfigured(): bool
    {
        return (bool) config('retail.fallbacks.openwebninja.key');
    }

    public function supportsPincode(): bool
    {
        return false;
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'X-RapidAPI-Key' => (string) config('retail.fallbacks.openwebninja.key'),
            'X-RapidAPI-Host' => $this->host(),
        ])->acceptJson()->timeout(30);
    }

    public function search(string $query, int $page = 1, string $sort = 'relevance', ?string $category = null): SearchPage
    {
        $j = $this->getJson($this->url('search'), ['query' => $query, 'page' => $page, 'country' => 'IN', 'sort_by' => self::SORTS[$sort] ?? 'RELEVANCE'], 'search', "$query#$page");
        $rows = array_values(array_filter(array_map(fn ($r) => FallbackMapper::listing((array) $r), (array) ($j['data']['products'] ?? []))));

        return (new SearchPage($rows, (int) ($j['data']['total_products'] ?? count($rows))))->withProvider($this->name(), false);
    }

    public function product(string $id): SourceProduct
    {
        $j = $this->getJson($this->url('product-details'), ['asin' => $id, 'country' => 'IN'], 'product', $id);
        $data = FallbackMapper::product($id, (array) ($j['data'] ?? []));
        if ($data['title'] === '') {
            throw new SourceException("openwebninja returned no product for $id");
        }

        return new SourceProduct($data, $this->name(), false);
    }

    public function bestsellers(string $categoryId, int $page = 1): SearchPage
    {
        $j = $this->getJson($this->url('best-sellers'), ['category' => $categoryId, 'page' => $page, 'country' => 'IN'], 'bestsellers', "$categoryId#$page");
        $rows = array_values(array_filter(array_map(fn ($r) => FallbackMapper::listing((array) $r), (array) ($j['data']['best_sellers'] ?? []))));

        return (new SearchPage($rows, count($rows)))->withProvider($this->name(), false);
    }

    private function host(): string
    {
        return (string) config('retail.fallbacks.openwebninja.host');
    }

    private function url(string $path): string
    {
        return 'https://'.$this->host().'/'.$path;
    }
}
