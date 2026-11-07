<?php

// Fulfilment admin print views (session auth; staff only). Required from routes/web.php.

use App\Http\Controllers\Admin\HubLabelController;
use App\Http\Middleware\AdminGuard;
use Illuminate\Support\Facades\Route;

// AdminGuard: same IP allowlist and idle timeout as the admin panel.
Route::get('/hub/labels', HubLabelController::class)->middleware(AdminGuard::class)->name('hub.labels');
