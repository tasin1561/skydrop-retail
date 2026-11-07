<?php

// Growth module routes (prefix api/v1): affiliate program, refer a friend, shared wishlists. See docs/API.md.

use App\Http\Controllers\Api\Growth\AffiliateController;
use App\Http\Controllers\Api\Growth\ReferralController;
use App\Http\Controllers\Api\Growth\SharedWishlistController;
use Illuminate\Support\Facades\Route;

// ---- Public ----
Route::post('r/click', [AffiliateController::class, 'click'])->middleware('throttle:60,1');
Route::get('affiliate/program', [AffiliateController::class, 'program']);
Route::get('wishlists/{code}', [SharedWishlistController::class, 'show'])->middleware('throttle:60,1');

// ---- Signed-in shopper ----
Route::middleware('auth:sanctum')->group(function () {
    Route::post('affiliate/apply', [AffiliateController::class, 'apply'])->middleware('throttle:10,1');
    Route::get('affiliate/me', [AffiliateController::class, 'me']);
    Route::patch('affiliate/me', [AffiliateController::class, 'update']);
    Route::post('affiliate/payouts', [AffiliateController::class, 'requestPayout'])->middleware('throttle:10,1');

    Route::get('referrals/me', [ReferralController::class, 'me']);
    Route::post('wishlist/share', [SharedWishlistController::class, 'share']);
});
