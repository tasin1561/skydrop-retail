<?php

namespace App\Jobs\Commerce;

use App\Services\Commerce\PointsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePoints implements ShouldQueue
{
    use Queueable;

    public function handle(PointsService $points): void
    {
        $points->expire();
    }
}
