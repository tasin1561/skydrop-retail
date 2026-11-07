<?php

// Fulfilment module routes (prefix api/v1): uploads, tracking map, unboxing approval, returns, price-difference payments.
// Each feature is switchable in Admin → System → Features (route middleware "feature:key").

use App\Http\Controllers\Api\Fulfilment\FulfilmentController as F;
use Illuminate\Support\Facades\Route;

// Signed link to a private upload (return photos); the URL itself is the credential.
Route::get('uploads/{upload}', [F::class, 'showUpload'])->middleware('signed')->name('uploads.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('uploads', [F::class, 'upload'])->middleware(['feature:returns', 'throttle:30,1']);

    Route::get('orders/{id}/tracking', [F::class, 'tracking'])->middleware('feature:live_tracking_map');

    Route::middleware('feature:unboxing')->group(function () {
        Route::get('orders/{id}/unboxing', [F::class, 'unboxing']);
        Route::post('orders/{id}/unboxing/approve', [F::class, 'approveUnboxing']);
        Route::post('orders/{id}/unboxing/problem', [F::class, 'unboxingProblem']);
    });

    Route::middleware('feature:returns')->group(function () {
        Route::get('returns', [F::class, 'returns']);
        Route::get('orders/{id}/returns', [F::class, 'orderReturns']);
        Route::post('orders/{id}/returns', [F::class, 'createReturn'])->middleware('throttle:10,1');
    });

    Route::post('orders/{id}/pay-difference', [F::class, 'payDifference']);
});
