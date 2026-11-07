<?php

namespace App\Jobs\Fulfilment;

use App\Services\Fulfilment\UnboxingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Hourly: unboxings without a customer answer after the configured window (default 48 h) are approved. */
class AutoApproveUnboxing implements ShouldQueue
{
    use Queueable;

    public function handle(UnboxingService $unboxing): void
    {
        $unboxing->autoApproveDue();
    }
}
