<?php

namespace App\Jobs\Tracking;

use App\Models\TrackingEvent;
use App\Services\Tracking\TrackingService;
use App\Support\Features;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Sends one tracking_events row to its destination. Retries network / 429 / 5xx errors with backoff. */
class SendTrackingEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    /** Seconds before attempt 2, 3, 4, 5. */
    public const BACKOFF = [10, 60, 300, 1800];

    public function __construct(public int $trackingEventId) {}

    public function handle(TrackingService $tracking): void
    {
        $row = TrackingEvent::query()->find($this->trackingEventId);
        if (! $row || in_array($row->status, ['sent', 'skipped'], true)) {
            return;
        }
        if (! Features::enabled('tracking')) {
            $row->forceFill(['status' => 'skipped', 'error' => 'Tracking feature switched off'])->save();

            return;
        }
        $attempt = $this->attempts();
        $row->attempts = $attempt;
        try {
            $r = $tracking->driver($row->destination)->send($row->payload ?? []);
            $row->response_code = $r['code'];
            $row->status = $r['error'] ? 'failed' : 'sent';
            $row->error = $r['error'];
            $row->sent_at = $r['error'] ? null : now();
            $row->save();
        } catch (Throwable $e) {
            $row->error = mb_substr($e->getMessage(), 0, 1000);
            if ($attempt >= $this->tries) {
                $row->status = 'failed';
                $row->save();

                return;
            }
            $row->save();
            $this->release(self::BACKOFF[$attempt - 1] ?? 1800);
        }
    }

    public function failed(?Throwable $e): void
    {
        TrackingEvent::query()->whereKey($this->trackingEventId)->where('status', 'queued')
            ->update(['status' => 'failed', 'error' => mb_substr((string) $e?->getMessage(), 0, 1000)]);
    }
}
