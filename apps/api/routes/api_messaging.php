<?php

// Messaging module routes (prefix api/v1): notification centre, preferences, push devices, cart sync,
// email verification and unsubscribe. See docs/API.md "Notifications".

use App\Http\Controllers\Api\Messaging\CartController;
use App\Http\Controllers\Api\Messaging\NotificationController;
use App\Http\Controllers\Api\Messaging\PreferenceController;
use App\Http\Controllers\Api\Messaging\PushController;
use Illuminate\Support\Facades\Route;

Route::get('email/verify/{user}/{hash}', [PreferenceController::class, 'verify'])->middleware(['signed', 'throttle:20,1'])->name('messaging.email.verify');
Route::match(['get', 'post'], 'unsubscribe/{user}/{channel}', [PreferenceController::class, 'unsubscribe'])->middleware(['signed', 'throttle:20,1'])->name('messaging.unsubscribe');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me/notifications', [PreferenceController::class, 'show']);
    Route::patch('me/notifications', [PreferenceController::class, 'update']);
    Route::post('me/email/verification', [PreferenceController::class, 'sendVerification'])->middleware('throttle:6,1');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->whereNumber('id');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->whereNumber('id');

    Route::post('push/expo-token', [PushController::class, 'expoToken'])->middleware('throttle:30,1');
    Route::delete('push/expo-token', [PushController::class, 'removeExpoToken']);
    Route::post('push/subscribe', [PushController::class, 'subscribe'])->middleware('throttle:30,1');
    Route::delete('push/subscribe', [PushController::class, 'unsubscribe']);

    Route::get('cart', [CartController::class, 'show']);
    Route::put('cart', [CartController::class, 'update'])->middleware('throttle:60,1');
});
