<?php

namespace App\Jobs\Fulfilment;

use App\Services\Fulfilment\PriceAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Compares pending order items of one product with its (just refreshed) products row. */
class CheckSourcePriceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $productId) {}

    public function handle(PriceAlertService $alerts): void
    {
        if ($alerts->enabled()) {
            $alerts->checkProduct($this->productId);
        }
    }
}
