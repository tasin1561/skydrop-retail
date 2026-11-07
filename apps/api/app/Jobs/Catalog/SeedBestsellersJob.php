<?php

namespace App\Jobs\Catalog;

use App\Services\Catalog\CatalogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Weekly: bestseller pages per active category (live_fetch marketplaces only). */
class SeedBestsellersJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public string $market) {}

    public function handle(CatalogService $catalog): void
    {
        Log::info("catalog: seeded {$catalog->seedBestsellers($this->market)} {$this->market} bestsellers");
    }
}
