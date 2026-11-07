<?php

namespace App\Services\Messaging\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Expo push service: POST https://exp.host/--/api/v2/push/send with up to 100 messages per request. */
class ExpoPush
{
    public static function isToken(string $token): bool
    {
        return (bool) preg_match('/^(ExponentPushToken|ExpoPushToken)\[[^\]]+\]$/', $token);
    }

    /** @param iterable<PushSubscription> $subscriptions */
    public function send(iterable $subscriptions, string $title, string $body, array $data = []): PushResult
    {
        $result = new PushResult;
        $subs = collect($subscriptions)->values();
        foreach ($subs->chunk(100) as $chunk) {
            $chunk = $chunk->values();
            $messages = $chunk->map(fn (PushSubscription $s) => [
                'to' => $s->token, 'title' => $title, 'body' => $body, 'data' => (object) $data,
                'sound' => 'default', 'priority' => 'high', 'channelId' => 'default',
            ])->all();
            try {
                $req = Http::timeout(15)->acceptJson()->asJson();
                if ($t = config('messaging.push.expo_access_token')) {
                    $req = $req->withToken($t);
                }
                $res = $req->post(config('messaging.push.expo_url'), $messages);
            } catch (Throwable $e) {
                $result->failed += $chunk->count();
                $result->errors[] = $e->getMessage();

                continue;
            }
            if (! $res->successful()) {
                $result->failed += $chunk->count();
                $result->errors[] = 'Expo HTTP '.$res->status();

                continue;
            }
            foreach ((array) $res->json('data', []) as $i => $ticket) {
                $sub = $chunk[$i] ?? null;
                if (($ticket['status'] ?? null) === 'ok') {
                    $result->sent++;
                    $result->ids[] = (string) ($ticket['id'] ?? '');
                    $sub?->forceFill(['last_used_at' => now()])->save();
                } else {
                    $result->failed++;
                    $err = $ticket['details']['error'] ?? ($ticket['message'] ?? 'error');
                    $result->errors[] = $err;
                    if ($err === 'DeviceNotRegistered') {
                        $sub?->delete(); // app uninstalled or token rotated
                    }
                }
            }
        }
        if ($result->errors) {
            Log::info('[push:expo] '.implode('; ', array_unique($result->errors)));
        }

        return $result;
    }
}
