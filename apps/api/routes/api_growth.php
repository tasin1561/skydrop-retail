<?php

// Growth module routes (prefix api/v1): affiliate program, refer a friend, shared wishlists. See docs/API.md.
// Each group is switchable in Admin → System → Features (feature:affiliate | feature:referral | feature:share → 404 when off).

use App\Http\Controllers\Api\Growth\AffiliateController;
use App\Http\Controllers\Api\Growth\ReferralController;
use App\Http\Controllers\Api\Growth\SharedWishlistController;
use Illuminate\Support\Facades\Route;

// ---- Affiliate program ----
Route::middleware('feature:affiliate')->group(function () {
    Route::post('r/click', [AffiliateController::class, 'click'])->middleware('throttle:60,1');
    Route::get('affiliate/program', [AffiliateController::class, 'program']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('affiliate/apply', [AffiliateController::class, 'apply'])->middleware('throttle:10,1');
        Route::get('affiliate/me', [AffiliateController::class, 'me']);
        Route::patch('affiliate/me', [AffiliateController::class, 'update']);
        Route::post('affiliate/payouts', [AffiliateController::class, 'requestPayout'])->middleware('throttle:10,1');
    });
});

// ---- Refer a friend ----
Route::middleware(['feature:referral', 'auth:sanctum'])->get('referrals/me', [ReferralController::class, 'me']);

// ---- Sharing (public wishlists) ----
Route::middleware('feature:share')->group(function () {
    Route::get('wishlists/{code}', [SharedWishlistController::class, 'show'])->middleware('throttle:60,1');
    Route::middleware('auth:sanctum')->post('wishlist/share', [SharedWishlistController::class, 'share']);
});
