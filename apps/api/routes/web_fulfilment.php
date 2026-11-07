<?php

// Fulfilment admin print views (session auth; staff only). Required from routes/web.php.

use App\Http\Controllers\Admin\HubLabelController;
use Illuminate\Support\Facades\Route;

Route::get('/hub/labels', HubLabelController::class)->name('hub.labels');
