<?php

namespace App\Jobs\Growth;

use App\Services\Growth\AffiliateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Hourly: approves commissions whose post-delivery hold has passed. */
class ApproveAffiliateCommissions implements ShouldQueue
{
    use Queueable;

    public function handle(AffiliateService $affiliates): void
    {
        $affiliates->approveDue();
    }
}
