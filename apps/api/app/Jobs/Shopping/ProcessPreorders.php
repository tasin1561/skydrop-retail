<?php

namespace App\Jobs\Shopping;

use App\Services\Shopping\PreorderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Hourly: pre-orders whose items are back in stock get a "pay the balance" reminder (feature "preorders"). */
class ProcessPreorders implements ShouldQueue
{
    use Queueable;

    public function handle(PreorderService $preorders): void
    {
        $preorders->process();
    }
}
