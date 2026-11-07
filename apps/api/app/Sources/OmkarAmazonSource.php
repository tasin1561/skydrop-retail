<?php

namespace App\Sources;

use App\Sources\Exceptions\SourceException;
use App\Sources\Support\HttpJsonSource;
use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Omkar Amazon Scraper API fallback (country IN, no Kolkata pincode). Field names follow the public docs
 * (product, country; title, price, images, rating, bullets, details...) and are read defensively.
 */
class OmkarAmazonSource extends AbstractSource
{
    use HttpJsonSource;

    public function name(): string
    {
        return 'omkar';
    }

    public function market(): string
    {
        return 'amazon';
    }

    public function isConfigured(): bool
    {
        return (bool) config('retail.fallbacks.omkar.key');
    }

    public function supportsPincode(): bool
    {
        return false;
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders(['API-Key' => (string) config('retail.fallbacks.omkar.key')])->acceptJson()->timeout(30);
    }

    public function search(string $query, int $page = 1, string $sort = 'relevance', ?string $category = null): SearchPage
    {
        $j = $this->getJson($this->base().'/products/search', ['query' => $query, 'page' => $page, 'country' => 'IN'], 'search', "$query#$page");
        $rows = array_values(array_filter(array_map(fn ($r) => FallbackMapper::listing((array) $r), (array) ($j['results'] ?? $j['products'] ?? $j['data'] ?? []))));

        return (new SearchPage($rows, (int) ($j['count'] ?? count($rows))))->withProvider($this->name(), false);
    }

    public function product(string $id): SourceProduct
    {
        $j = $this->getJson($this->base().'/products/details', ['product' => $id, 'country' => 'IN'], 'product', $id);
        $data = FallbackMapper::product($id, (array) ($j['data'] ?? $j));
        if ($data['title'] === '') {
            throw new SourceException("omkar returned no product for $id");
        }

        return new SourceProduct($data, $this->name(), false);
    }

    public function bestsellers(string $categoryId, int $page = 1): SearchPage
    {
        return $this->search($categoryId, $page);
    }

    private function base(): string
    {
        return rtrim((string) config('retail.fallbacks.omkar.url'), '/');
    }
}
