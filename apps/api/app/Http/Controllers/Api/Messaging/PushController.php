<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\Messaging\Push\ExpoPush;
use App\Services\Messaging\Push\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    /** POST /push/expo-token { token, platform? } */
    public function expoToken(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255'], 'platform' => ['nullable', 'string', 'in:ios,android,web']]);
        if (! ExpoPush::isToken($data['token'])) {
            return response()->json(['message' => 'Not an Expo push token.', 'errors' => ['token' => ['Not an Expo push token.']]], 422);
        }
        $s = PushSubscription::register($request->user()->id, 'expo', $data['token'], null, $data['platform'] ?? null);

        return response()->json(['ok' => true, 'id' => $s->id], 201);
    }

    /** DELETE /push/expo-token { token } (sign-out on the device) */
    public function removeExpoToken(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);
        PushSubscription::query()->where('user_id', $request->user()->id)->where('token_hash', hash('sha256', 'expo|'.$data['token']))->delete();

        return response()->json(['ok' => true]);
    }

    /** POST /push/subscribe — browser PushSubscription.toJSON(): { endpoint, keys: { p256dh, auth } } */
    public function subscribe(Request $request, WebPushSender $web): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys' => ['required', 'array'], 'keys.p256dh' => ['required', 'string', 'max:200'], 'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        $s = PushSubscription::register($request->user()->id, 'web', $data['endpoint'], $data['keys'], 'web');

        return response()->json(['ok' => true, 'id' => $s->id, 'delivering' => $web->available(), 'vapidPublicKey' => config('messaging.push.vapid_public_key')], 201);
    }

    /** DELETE /push/subscribe { endpoint } */
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);
        PushSubscription::query()->where('user_id', $request->user()->id)->where('token_hash', hash('sha256', 'web|'.$data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }
}
