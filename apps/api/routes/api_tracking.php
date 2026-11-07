<?php

// Marketing tracking module routes (prefix api/v1). Switchable in Admin → Features: "tracking" and "feeds".

use App\Http\Controllers\Api\Tracking\FeedController;
use App\Http\Controllers\Api\Tracking\TrackController;
use Illuminate\Support\Facades\Route;

Route::post('track', [TrackController::class, 'store'])->middleware(['feature:tracking', 'throttle:120,1']);
Route::get('tracking/config', [TrackController::class, 'config'])->middleware(['feature:tracking', 'throttle:120,1']);
Route::get('feeds/meta.csv', [FeedController::class, 'meta'])->middleware(['feature:feeds', 'throttle:30,1']);
Route::get('feeds/google.xml', [FeedController::class, 'google'])->middleware(['feature:feeds', 'throttle:30,1']);
