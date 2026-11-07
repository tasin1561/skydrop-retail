<?php

// Ops module routes (prefix api/v1): security (honeypot, bot challenge) and QR codes. Required from routes/api.php
// before the catalog routes so these exact paths win over products/{id}. See docs/SECURITY.md.

use App\Http\Controllers\Api\Ops\SecurityController;
use Illuminate\Support\Facades\Route;

// Honeypots: never linked for humans (hidden link on the web, disallowed in robots.txt).
Route::get('products/export', [SecurityController::class, 'honeypot']);
Route::get('products/'.config('security.honeypot.product_id', 'SR-HONEYPOT-0001'), [SecurityController::class, 'honeypot']);

Route::post('security/challenge', [SecurityController::class, 'challenge'])->middleware('throttle:10,1');
Route::get('qr', [SecurityController::class, 'qr'])->middleware('throttle:30,1');
