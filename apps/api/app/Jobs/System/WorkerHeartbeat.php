<?php

namespace App\Jobs\System;

use App\Services\System\Heartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Queued every minute by the scheduler; proves a queue worker is running (System → Health, /health/ready). */
class WorkerHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Heartbeat::beat(Heartbeat::WORKER, ['queue' => $this->queue ?? 'default', 'pid' => getmypid() ?: null]);
    }
}
