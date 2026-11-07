<?php

// Money module routes (prefix api/v1): Skydrop Wallet, gift cards, Skydrop Plus, card EMI. See docs/API.md.
// Each block is behind its admin feature switch (App\Support\Features): wallet, gift_cards, membership, pay.emi.

use App\Http\Controllers\Api\Money\MoneyController;
use Illuminate\Support\Facades\Route;

Route::get('money/status', [MoneyController::class, 'status']);
Route::get('emi', [MoneyController::class, 'emi'])->middleware('throttle:120,1'); // returns enabled:false when pay.emi is off

Route::middleware('feature:gift_cards')->group(function () {
    Route::get('gift-cards/config', [MoneyController::class, 'giftCardConfig']);
});
Route::middleware('feature:membership')->group(function () {
    Route::get('membership/plans', [MoneyController::class, 'plans']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('wallet', [MoneyController::class, 'wallet'])->middleware('feature:wallet');

    Route::middleware('feature:gift_cards')->group(function () {
        Route::get('gift-cards', [MoneyController::class, 'giftCards']);
        Route::post('gift-cards', [MoneyController::class, 'buyGiftCard'])->middleware('throttle:10,1');
        // Redeeming credits the wallet, so it also needs the wallet switch. Throttled against code guessing.
        Route::post('gift-cards/redeem', [MoneyController::class, 'redeemGiftCard'])->middleware(['feature:wallet', 'throttle:10,1']);
    });

    Route::middleware('feature:membership')->group(function () {
        Route::get('membership', [MoneyController::class, 'membership']);
        Route::post('membership', [MoneyController::class, 'buyMembership'])->middleware('throttle:10,1');
    });
});
