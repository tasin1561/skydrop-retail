<?php

// Shopping module routes (prefix api/v1): price history, compare stores, bundles, group buy, pre-orders and
// product requests. Each group is behind its admin feature switch (route middleware "feature:key"). See docs/API.md "Shopping".

use App\Http\Controllers\Api\Shopping\BundleController;
use App\Http\Controllers\Api\Shopping\CompareController;
use App\Http\Controllers\Api\Shopping\GroupBuyController;
use App\Http\Controllers\Api\Shopping\PreorderController;
use App\Http\Controllers\Api\Shopping\PriceHistoryController;
use App\Http\Controllers\Api\Shopping\ProductRequestController;
use App\Http\Middleware\CartLookupGuard;
use Illuminate\Support\Facades\Route;

$shoppingId = '[A-Za-z0-9_-]{2,40}';

Route::middleware('feature:price_history')->group(function () use ($shoppingId) {
    Route::get('products/{id}/price-history', [PriceHistoryController::class, 'show'])->where('id', $shoppingId)->middleware('throttle:120,1');
});

Route::middleware('feature:compare_stores')->group(function () use ($shoppingId) {
    Route::get('products/{id}/compare', [CompareController::class, 'show'])->where('id', $shoppingId)->middleware('throttle:120,1');
    Route::post('checkout/alternatives', [CompareController::class, 'alternatives'])->middleware([CartLookupGuard::class, 'throttle:60,1']);
});

Route::middleware('feature:bundles')->group(function () use ($shoppingId) {
    Route::get('products/{id}/bought-together', [BundleController::class, 'boughtTogether'])->where('id', $shoppingId)->middleware('throttle:120,1');
    Route::get('products/{id}/bundles', [BundleController::class, 'forProduct'])->where('id', $shoppingId);
    Route::get('bundles', [BundleController::class, 'index']);
    Route::get('bundles/{slug}', [BundleController::class, 'show'])->where('slug', '[a-z0-9-]{1,80}');
});

Route::middleware('feature:group_orders')->group(function () {
    Route::get('groups/{code}', [GroupBuyController::class, 'show'])->where('code', '[A-Za-z0-9]{6,12}');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('groups', [GroupBuyController::class, 'index']);
        Route::post('groups', [GroupBuyController::class, 'store'])->middleware('throttle:10,1');
        Route::post('groups/{code}/join', [GroupBuyController::class, 'join'])->middleware('throttle:30,1');
        Route::post('groups/{code}/leave', [GroupBuyController::class, 'leave']);
        Route::post('groups/{code}/close', [GroupBuyController::class, 'close']);
        Route::post('groups/{code}/cancel', [GroupBuyController::class, 'cancel']);
    });
});

// Pre-order info also carries the restock waitlist count (feature "waitlist"); placing one needs "preorders".
Route::get('products/{id}/preorder', [PreorderController::class, 'show'])->where('id', $shoppingId);
Route::post('preorders', [PreorderController::class, 'store'])->middleware(['feature:preorders', 'auth:sanctum', 'throttle:20,1']);

Route::middleware('feature:product_requests')->group(function () {
    Route::post('product-requests', [ProductRequestController::class, 'store'])->middleware('throttle:10,1');
    Route::get('product-requests', [ProductRequestController::class, 'index'])->middleware('auth:sanctum');
});
