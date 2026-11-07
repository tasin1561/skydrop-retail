<?php

namespace App\Jobs\Messaging;

use App\Models\User;
use App\Services\Messaging\BroadcastAudience;
use App\Services\Messaging\Messenger;
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
            BroadcastAudience::query($this->segment)->select('id')->chunkById(200, function ($users) {
                dispatch(new self($this->broadcastId, $this->segment, $this->channels, $this->subject, $this->body, $users->pluck('id')->all(), $this->link));
            });

            return;
        }
        User::query()->whereIn('id', $this->userIds)->get()->each(fn (User $u) => $messenger->raw($u, $this->channels, $this->subject, $this->body, 'marketing', [
            'broadcast_id' => $this->broadcastId, 'event' => 'broadcast', 'link' => $this->link,
        ]));
    }
}
