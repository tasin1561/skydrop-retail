<?php

use App\Support\Features;
use Illuminate\Support\Facades\Route;

// Ops: honeypot, bot challenge, QR codes (security module). Registered before the catalog so
// products/export is not taken by products/{id}.
require __DIR__.'/api_ops.php';

// Catalog: marketplaces, search, products, suggestions, fetch jobs (owned by the catalog module).
// catalog.guard: only the Next.js server (X-Internal-Key) or signed app clients (X-App-Key + HMAC); see docs/SECURITY.md.
Route::middleware('catalog.guard')->group(fn () => require __DIR__.'/api_catalog.php');

// Commerce: auth (OTP), account, addresses, wishlist, checkout, orders, payments, webhooks.
require __DIR__.'/api_commerce.php';

// Categories: unified category tree (GET /categories, GET /categories/{path}).
require __DIR__.'/api_categories.php';

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
Route::get('/features', fn () => ['features' => Features::all(), 'maintenance' => App\Services\System\Maintenance::state()]); // maintenance: System → Settings
require __DIR__.'/api_features.php'; // GET checkout/options (payment methods that are switched on)

// Shopping: price history, compare stores, bundles, group buy, pre-orders, product requests.
require __DIR__.'/api_shopping.php';

// Fulfilment: uploads, tracking map, unboxing approval, returns, price-difference payments.
require __DIR__.'/api_fulfilment.php';

Route::get('/health', fn () => ['ok' => true, 'time' => now()->toIso8601String()]);
require __DIR__.'/api_system.php'; // GET health/ready, health/details (System module)
