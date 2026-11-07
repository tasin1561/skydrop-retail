<?php

namespace App\Jobs\Messaging;

use App\Models\User;
use App\Services\Messaging\BroadcastAudience;
use App\Services\Messaging\Messenger;
use App\Support\Limits;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Marketing broadcast. Without $userIds it splits the segment into chunks of 200 and queues one job per chunk;
 * each chunk sends through Messenger::raw (marketing: opt-outs and quiet hours apply).
 */
class SendBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public string $broadcastId,
        public array $segment,
        public array $channels,
        public string $subject,
        public string $body,
        public ?array $userIds = null,
        public ?string $link = null,
    ) {
        $this->onQueue(config('messaging.queue.marketing'));
    }

    public function handle(Messenger $messenger): void
    {
        if ($this->userIds === null) {
            $left = max(1, Limits::int('messaging.broadcast_max_recipients')); // System → Limits
            BroadcastAudience::query($this->segment)->select('id')->chunkById(200, function ($users) use (&$left) {
                $ids = array_slice($users->pluck('id')->all(), 0, $left);
                $left -= count($ids);
                dispatch(new self($this->broadcastId, $this->segment, $this->channels, $this->subject, $this->body, $ids, $this->link));

                return $left > 0;
            });

            return;
        }
        User::query()->whereIn('id', $this->userIds)->get()->each(fn (User $u) => $messenger->raw($u, $this->channels, $this->subject, $this->body, 'marketing', [
            'broadcast_id' => $this->broadcastId, 'event' => 'broadcast', 'link' => $this->link,
        ]));
    }
}
