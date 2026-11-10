<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogService;
use Illuminate\Console\Command;

class CatalogSeedBestsellers extends Command
{
    protected $signature = 'retail:seed-bestsellers {market : amazon|flipkart|myntra}';

    protected $description = 'Fetch bestseller pages for every active category of a live marketplace';

    public function handle(CatalogService $catalog): int
    {
        $market = (string) $this->argument('market');
        if (! $catalog->isLive($market)) {
            $this->warn("$market is not enabled with live_fetch: nothing fetched (local catalog only).");

            return self::SUCCESS;
        }
        $this->info("Upserted {$catalog->seedBestsellers($market)} $market listings.");

        return self::SUCCESS;
    }
}
