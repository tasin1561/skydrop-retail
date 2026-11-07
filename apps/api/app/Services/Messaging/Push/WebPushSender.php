<?php

namespace App\Services\Messaging\Push;

use App\Models\PushSubscription;

/**
 * Web Push (VAPID). Implementations need the minishlink/web-push package, which is not installed;
 * the container binds NullWebPushSender until it is. TODO(web-push): composer require minishlink/web-push,
 * implement this interface with Minishlink\WebPush\WebPush using messaging.push.vapid_* and bind it in
 * MessagingServiceProvider. Remove subscriptions that answer 404/410.
 */
interface WebPushSender
{
    /** @param iterable<PushSubscription> $subscriptions */
    public function send(iterable $subscriptions, array $payload): PushResult;

    public function available(): bool;
}
