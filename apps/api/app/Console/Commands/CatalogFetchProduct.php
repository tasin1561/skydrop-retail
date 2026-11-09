<?php

namespace App\Console\Commands;

use App\Http\Resources\ProductResource;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\MarketResolver;
use App\Services\Catalog\ProviderChain;
use App\Sources\Support\UsageContext;
use Illuminate\Console\Command;

/** Debug: fetch one product through the provider chain now (ignores live_fetch and cache) and print it. */
class CatalogFetchProduct extends Command
{
    protected $signature = 'retail:fetch-product {id : ASIN, itm id or bb id} {--dry : parse and print without saving}';

    protected $description = 'Fetch and parse one product from its provider (debug)';

    public function handle(CatalogService $catalog, ProviderChain $chain, MarketResolver $markets): int
    {
        $id = (string) $this->argument('id');
        $market = $markets->forId($id);
        if (! $market) {
            $this->error('Unknown id format.');

            return self::FAILURE;
        }
        $t = microtime(true);
        if ($this->option('dry')) {
            $sp = UsageContext::run('refresh', false, fn () => $chain->product($market, $id, 'browse'));
            if (! $sp) {
                $this->error('All providers failed (see api_logs / provider_health).');

                return self::FAILURE;
            }
            $this->line(json_encode(['provider' => $sp->provider, 'pincodeVerified' => $sp->pincodeVerified] + $sp->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $p = UsageContext::run('refresh', false, fn () => $catalog->refreshProduct($id, interactive: true));
            if (! $p) {
                $this->error('All providers failed (see api_logs / provider_health).');

                return self::FAILURE;
            }
            $this->line(json_encode((new ProductResource($p->load('details')))->resolve(request()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $this->info(sprintf('Done in %.1fs', microtime(true) - $t));

        return self::SUCCESS;
    }
}
