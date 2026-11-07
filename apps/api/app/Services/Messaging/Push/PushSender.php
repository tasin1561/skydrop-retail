<?php

namespace App\Services\Messaging\Push;

use App\Models\PushSubscription;
use App\Models\User;

/** Sends to every device of a user: Expo tokens (mobile app) and Web Push subscriptions (browser). */
class PushSender
{
    public function __construct(private ExpoPush $expo, private WebPushSender $web) {}

    public function hasDevices(User $user): bool
    {
        return PushSubscription::query()->where('user_id', $user->id)
            ->when(! $this->web->available(), fn ($q) => $q->where('kind', 'expo'))->exists();
    }

    public function toUser(User $user, string $title, string $body, array $data = []): PushResult
    {
        $subs = PushSubscription::query()->where('user_id', $user->id)->get();
        $expo = $this->expo->send($subs->where('kind', 'expo'), $title, $body, $data);
        $web = $subs->where('kind', 'web');
        if ($web->isNotEmpty() && $this->web->available()) {
            $w = $this->web->send($web, ['title' => $title, 'body' => $body, 'data' => $data]);

            return new PushResult($expo->sent + $w->sent, $expo->failed + $w->failed, array_merge($expo->ids, $w->ids), array_merge($expo->errors, $w->errors));
        }

        return $expo;
    }
}
