<?php

// CMS module routes (prefix api/v1): home banners and flash sales. See docs/API.md "CMS".

use App\Http\Controllers\Api\Cms\CmsController;
use Illuminate\Support\Facades\Route;

Route::get('cms/home', [CmsController::class, 'home'])->middleware('throttle:240,1');
