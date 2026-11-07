<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---- Commerce module schedule ----
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Commerce\ExpirePoints)->dailyAt('02:15')->name('commerce:expire-points')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Commerce\CheckProductWatches)->hourly()->name('commerce:check-watches')->onOneServer();
