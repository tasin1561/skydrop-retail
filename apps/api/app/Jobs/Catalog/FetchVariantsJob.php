<?php

namespace App\Jobs\Catalog;

use App\Models\Product;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\JobTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Fetch a product's siblings: phase "colours" (one per colour, so swatches open instantly) and then a separate
 * queued phase "sizes", which POST /products/{id}/cancel-variants drops when the shopper bounces.
 */
class FetchVariantsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $productId, public string $phase = 'colours', public ?string $fetchJobId = null) {}

    public function handle(CatalogService $catalog, JobTracker $jobs): void
    {
        $jobs->running($this->fetchJobId);
        try {
            $p = Product::query()->with('details')->find($this->productId);
            if (! $p) {
                $jobs->failed($this->fetchJobId, 'Product not found');

                return;
            }
            $plan = $catalog->variantPlan($p);
            if ($this->phase === 'colours') {
                $n = $catalog->fetchSiblings($p, $plan['colours']);
                if ($plan['sizes'] && ! $catalog->variantsCancelled($p->id)) {
                    dispatch(new self($p->id, 'sizes', $this->fetchJobId));

                    return; // the fetch_jobs row stays "running" until sizes finish
                }
                $jobs->done($this->fetchJobId, ['colours' => $n, 'sizes' => 0]);

                return;
            }
            if ($catalog->variantsCancelled($p->id)) {
                $jobs->done($this->fetchJobId, ['cancelled' => true]);

                return;
            }
            $jobs->done($this->fetchJobId, ['sizes' => $catalog->fetchSiblings($p, $plan['sizes'])]);
        } catch (Throwable $e) {
            $jobs->failed($this->fetchJobId, $e->getMessage());
            throw $e;
        }
    }
}
