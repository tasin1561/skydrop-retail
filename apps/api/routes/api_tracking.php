<?php

// Marketing tracking module routes (prefix api/v1). See docs/API.md "Tracking".

use App\Http\Controllers\Api\Tracking\FeedController;
use App\Http\Controllers\Api\Tracking\TrackController;
use Illuminate\Support\Facades\Route;

Route::post('track', [TrackController::class, 'store'])->middleware('throttle:120,1');
Route::get('tracking/config', [TrackController::class, 'config'])->middleware('throttle:120,1');
Route::get('feeds/meta.csv', [FeedController::class, 'meta'])->middleware('throttle:30,1');
Route::get('feeds/google.xml', [FeedController::class, 'google'])->middleware('throttle:30,1');
