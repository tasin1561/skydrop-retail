<?php

namespace App\Jobs\Fulfilment;

use App\Services\Fulfilment\PriceAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Scheduled every 2 h: refresh + re-check the source price of every ordered, not yet bought item. */
class CheckSupplierPrices implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $refresh = true) {}

    public function handle(PriceAlertService $alerts): void
    {
        $alerts->checkPending($this->refresh);
    }
}
