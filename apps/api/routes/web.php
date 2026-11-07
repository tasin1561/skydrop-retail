<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Fulfilment: hub box labels (print view).
require __DIR__.'/web_fulfilment.php';
