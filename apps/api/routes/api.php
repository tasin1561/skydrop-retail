<?php

use Illuminate\Support\Facades\Route;

// Catalog: marketplaces, search, products, suggestions, fetch jobs (owned by the catalog module).
require __DIR__.'/api_catalog.php';

// Commerce: auth (OTP), account, addresses, wishlist, checkout, orders, payments, webhooks.
require __DIR__.'/api_commerce.php';

Route::get('/health', fn () => ['ok' => true, 'time' => now()->toIso8601String()]);
