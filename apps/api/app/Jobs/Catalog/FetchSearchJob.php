<?php

namespace App\Jobs\Catalog;

use App\Services\Catalog\CatalogService;
use App\Services\Catalog\JobTracker;
use App\Sources\Support\UsageContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Fetch one store result page (~48 products, one provider call) and cache it. */
class FetchSearchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        public string $market,
        public string $query,
        public int $page = 1,
        public string $sort = 'relevance',
        public ?string $category = null,
        public ?string $fetchJobId = null,
        ?string $usagePurpose = null,
    ) {
        $this->usagePurpose = $usagePurpose;
    }

    /** Plain property with a default: jobs queued before it existed still run (see FetchProductJob). */
    public ?string $usagePurpose = null;

    public function handle(CatalogService $catalog, JobTracker $jobs): void
    {
        UsageContext::run($this->usagePurpose, $this->attempts() > 1, fn () => $this->fetch($catalog, $jobs));
    }

    private function fetch(CatalogService $catalog, JobTracker $jobs): void
    {
        $jobs->running($this->fetchJobId);
        try {
            $cache = $catalog->refreshSearch($this->market, $this->query, $this->page, $this->sort, $this->category);
            if (! $cache) {
                $jobs->failed($this->fetchJobId, 'No provider could fetch this search');

                return;
            }
            $jobs->done($this->fetchJobId, ['market' => $this->market, 'count' => count((array) json_decode($cache->product_ids, true)), 'total' => $cache->total], $cache->source);
        } catch (Throwable $e) {
            $jobs->failed($this->fetchJobId, $e->getMessage());
            throw $e;
        }
    }
}
