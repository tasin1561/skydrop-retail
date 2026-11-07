<?php

namespace Tests\Feature\Catalog;

use App\Models\Marketplace;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\MarketplaceSeeder;
use Database\Seeders\PricingRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait CatalogTestHelpers
{
    protected function seedCatalog(): void
    {
        $this->seed([MarketplaceSeeder::class, CategorySeeder::class, PricingRuleSeeder::class, CatalogSeeder::class]);
    }

    /** Turn on live fetching for a marketplace with a fake Oxylabs token. */
    protected function goLive(string $market = 'amazon'): void
    {
        Marketplace::query()->whereKey($market)->update(['live_fetch' => true]);
        Cache::forget("catalog:live:$market");
        config(['retail.oxylabs.token' => 'test-token', 'retail.oxylabs.hedge_after' => 0]);
    }

    protected function amazonHtml(): string
    {
        return (string) gzdecode((string) file_get_contents(__DIR__.'/../../Fixtures/amazon_B0FMDLD86P.html.gz'));
    }

    protected function oxylabsRaw(string $html, int $status = 200): array
    {
        return ['results' => [['content' => $html, 'status_code' => $status, 'type' => 'raw']]];
    }

    protected function fixtureJson(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/'.$name), true);
    }

    /** Oxylabs fake: product pages get the HTML fixture (with the requested ASIN), searches the parsed fixture. */
    protected function fakeOxylabs(?string $html = null): void
    {
        $html ??= $this->amazonHtml();
        Http::fake([
            'realtime.oxylabs.io/*' => function ($request) use ($html) {
                $body = $request->data();

                return match ($body['source'] ?? '') {
                    'amazon_product' => Http::response($this->oxylabsRaw($html)),
                    'amazon_search' => Http::response($this->fixtureJson('oxylabs_amazon_search_earbuds.json')),
                    'amazon_bestsellers' => Http::response($this->fixtureJson('oxylabs_amazon_bestsellers_electronics.json')),
                    default => Http::response(['message' => 'unexpected'], 400),
                };
            },
        ]);
    }
}
