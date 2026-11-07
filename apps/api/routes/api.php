<?php

use Illuminate\Support\Facades\Route;

// Catalog: marketplaces, search, products, suggestions, fetch jobs (owned by the catalog module).
require __DIR__.'/api_catalog.php';

// Commerce: auth (OTP), account, addresses, wishlist, checkout, orders, payments, webhooks.
require __DIR__.'/api_commerce.php';

// Growth: affiliate program, refer a friend, shared wishlists.
require __DIR__.'/api_growth.php';

// CMS: home banners, flash sales.
require __DIR__.'/api_cms.php';

// Tracking: POST /track (server-side events), GET /tracking/config, product feeds.
require __DIR__.'/api_tracking.php';

// Messaging: notification centre, notification prefs, push devices, cart sync, email verification.
require __DIR__.'/api_messaging.php';

// Money: Skydrop Wallet, gift cards, Skydrop Plus membership, card EMI.
require __DIR__.'/api_wallet.php';

// AI shopping assistant: streaming chat, feedback (feature "ai_assistant").
require __DIR__.'/api_assistant.php';

// Feature switches (Admin → System → Features) for web and mobile.
Route::get('/features', fn () => ['features' => \App\Support\Features::all()]);

// Shopping: price history, compare stores, bundles, group buy, pre-orders, product requests.
require __DIR__.'/api_shopping.php';

// Fulfilment: uploads, tracking map, unboxing approval, returns, price-difference payments.
require __DIR__.'/api_fulfilment.php';

Route::get('/health', fn () => ['ok' => true, 'time' => now()->toIso8601String()]);
