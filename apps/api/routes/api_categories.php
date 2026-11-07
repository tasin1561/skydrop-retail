<?php

use App\Http\Controllers\Api\Catalog\CategoryController;
use App\Http\Middleware\DetectCrawler;
use Illuminate\Support\Facades\Route;

// Unified category tree (prefix api/v1). Read-only, served from the cached tree; never reaches a provider.
Route::middleware(DetectCrawler::class)->group(function () {
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{path}', [CategoryController::class, 'show'])->where('path', '[a-z0-9\-/]+');
});
