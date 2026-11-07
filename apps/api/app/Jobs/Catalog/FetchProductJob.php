<?php

namespace App\Jobs\Catalog;

use App\Services\Catalog\CatalogService;
use App\Services\Catalog\JobTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Refresh one product from its provider (raw HTML + own parser for Amazon), then queue its variants. */
class FetchProductJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public string $productId, public ?string $fetchJobId = null, public bool $withVariants = true) {}

    public function handle(CatalogService $catalog, JobTracker $jobs): void
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
