<?php

namespace App\Services\Messaging\Push;

class NullWebPushSender implements WebPushSender
{
    public function send(iterable $subscriptions, array $payload): PushResult
    {
        $n = is_countable($subscriptions) ? count($subscriptions) : iterator_count($subscriptions);

        return new PushResult(0, $n, [], $n ? ['Web Push is not enabled on this server (minishlink/web-push missing)'] : []);
    }

    public function available(): bool
    {
        return false;
    }
}
