<?php

namespace App\Jobs\Catalog;

use App\Services\Catalog\CatalogService;
use App\Services\Catalog\JobTracker;
use App\Sources\Support\UsageContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Refresh one product from its provider (raw HTML + own parser for Amazon), then queue its variants. */
class FetchProductJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * UsageContext purpose (refresh, watch, paste-link); null = product page. A plain property with a default, not a
     * constructor one: jobs queued before it existed are unserialized without it (the constructor doesn't run) and
     * would fail with "must not be accessed before initialization".
     */
    public ?string $usagePurpose = null;

    public function __construct(public string $productId, public ?string $fetchJobId = null, public bool $withVariants = true,
        ?string $usagePurpose = null)
    {
        $this->usagePurpose = $usagePurpose;
    }

    public function handle(CatalogService $catalog, JobTracker $jobs): void
    {
        // A queue retry of this job is a retried live call.
        UsageContext::run($this->usagePurpose, $this->attempts() > 1, fn () => $this->fetch($catalog, $jobs));
    }

    private function fetch(CatalogService $catalog, JobTracker $jobs): void
    {
        $jobs->running($this->fetchJobId);
        try {
            $p = $catalog->refreshProduct($this->productId);
            if (! $p) {
                $jobs->failed($this->fetchJobId, 'No provider could fetch this product');

                return;
            }
            $variantsJob = $this->withVariants ? $catalog->queueVariants($p->id) : null;
            $jobs->done($this->fetchJobId, ['productId' => $p->id, 'detailed' => $p->detailed, 'hidden' => $p->hidden, 'variantsJobId' => $variantsJob], $p->source);
        } catch (Throwable $e) {
            $jobs->failed($this->fetchJobId, $e->getMessage());
            throw $e;
        }
    }
}
