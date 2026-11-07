<?php

// Commerce module routes (prefix api/v1). See docs/API.md.

use App\Http\Controllers\Api\Commerce\AddressController;
use App\Http\Controllers\Api\Commerce\AuthController;
use App\Http\Controllers\Api\Commerce\CheckoutController;
use App\Http\Controllers\Api\Commerce\OrderController;
use App\Http\Controllers\Api\Commerce\PaymentController;
use App\Http\Controllers\Api\Commerce\PointsController;
use App\Http\Controllers\Api\Commerce\ReviewController;
use App\Http\Controllers\Api\Commerce\SkydropWebhookController;
use App\Http\Controllers\Api\Commerce\SupportController;
use App\Http\Controllers\Api\Commerce\WatchController;
use App\Http\Controllers\Api\Commerce\WishlistController;
use App\Http\Middleware\CartLookupGuard;
use Illuminate\Support\Facades\Route;

// ---- Auth (phone OTP) ----
Route::post('auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:20,1');
Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:30,1');

// ---- Public commerce ----
Route::post('checkout/quote', [CheckoutController::class, 'quote'])->middleware([CartLookupGuard::class, 'throttle:120,1']);
// Live provider calls per item (Oxylabs, not capped by the daily budget): signed clients only (Next.js server / app).
Route::post('checkout/verify', [CheckoutController::class, 'verify'])->middleware(['catalog.guard', 'throttle:20,1']);
Route::get('products/{id}/reviews', [ReviewController::class, 'index'])->middleware('feature:reviews');

// ---- Webhooks & payment pages ----
Route::match(['get', 'post'], 'webhooks/payments/{gateway}', [PaymentController::class, 'callback'])
    ->whereIn('gateway', ['bkash', 'nagad', 'sslcommerz', 'card', 'mock'])->name('payments.callback');
Route::post('webhooks/skydrop', SkydropWebhookController::class)->name('webhooks.skydrop');
Route::get('invoices/{id}', [OrderController::class, 'signedInvoice'])->middleware('signed')->name('orders.invoice.signed');
Route::get('payments/mock/{payment}', [PaymentController::class, 'mockShow'])->middleware('signed')->name('payments.mock.show');

// ---- Signed-in shopper ----
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::patch('me', [AuthController::class, 'update']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::patch('addresses/{id}', [AddressController::class, 'update']);
    Route::delete('addresses/{id}', [AddressController::class, 'destroy']);

    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::post('wishlist', [WishlistController::class, 'store']);
    Route::delete('wishlist/{productId}', [WishlistController::class, 'destroy']);

    Route::get('watches', [WatchController::class, 'index']);
    Route::post('watches', [WatchController::class, 'store']);
    Route::delete('watches/{productId}', [WatchController::class, 'destroy']);

    Route::post('products/{id}/reviews', [ReviewController::class, 'store'])->middleware('feature:reviews');

    Route::get('points', [PointsController::class, 'show']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{id}/pay', [OrderController::class, 'pay']);
    Route::get('orders/{id}/invoice', [OrderController::class, 'invoice']);
    Route::post('orders/{id}/support', [SupportController::class, 'store']);
    Route::get('support', [SupportController::class, 'index']);
});
