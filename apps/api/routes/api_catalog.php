<?php

use App\Http\Controllers\Api\Catalog\JobController;
use App\Http\Controllers\Api\Catalog\MarketplaceController;
use App\Http\Controllers\Api\Catalog\OfferController;
use App\Http\Controllers\Api\Catalog\ProductController;
use App\Http\Controllers\Api\Catalog\SearchController;
use App\Http\Middleware\DetectCrawler;
use Illuminate\Support\Facades\Route;

// Catalog module (prefix api/v1). Crawlers are detected so they never trigger provider fetches.
Route::middleware(DetectCrawler::class)->group(function () {
    Route::get('marketplaces', [MarketplaceController::class, 'index']);
    Route::get('suggest', [SearchController::class, 'suggest']);
    Route::get('offers', [OfferController::class, 'index']);
    Route::get('jobs/{id}', [JobController::class, 'show']);

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/popular', [ProductController::class, 'popular']);
    Route::get('products/deals', [ProductController::class, 'deals']);
    Route::get('products/{id}/related', [ProductController::class, 'related']);
    Route::post('products/{id}/cancel-variants', [ProductController::class, 'cancelVariants']);

    // 60 requests / minute / IP on the endpoints that can reach a provider.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('search', [SearchController::class, 'search']);
        Route::get('products/{id}', [ProductController::class, 'show'])->where('id', '[A-Za-z0-9_-]{2,40}');
    });
    Route::post('products/resolve', [ProductController::class, 'resolve'])->middleware('throttle:20,1');
});
