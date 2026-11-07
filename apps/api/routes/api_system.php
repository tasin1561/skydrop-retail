<?php

// System module (prefix api/v1): readiness and detailed health for uptime monitors. GET /health (liveness) stays in
// routes/api.php. See docs/OPERATIONS.md.

use App\Http\Controllers\Api\System\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('health/ready', [HealthController::class, 'ready'])->middleware('throttle:120,1');
Route::get('health/details', [HealthController::class, 'details'])->middleware('throttle:30,1');
