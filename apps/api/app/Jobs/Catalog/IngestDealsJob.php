<?php

namespace App\Jobs\Catalog;

use App\Services\Catalog\CatalogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Every 6 h: the store's deals page, then details (offers, coupons, lightning timers) for the top deals. */
class IngestDealsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public string $market) {}

    public function handle(CatalogService $catalog): void
    {
        Log::info("catalog: ingested {$catalog->ingestDeals($this->market)} {$this->market} deals");
    }
}
