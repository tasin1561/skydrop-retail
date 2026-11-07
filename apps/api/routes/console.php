<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---- Commerce module schedule ----
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Commerce\ExpirePoints)->dailyAt('02:15')->name('commerce:expire-points')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Commerce\CheckProductWatches)->hourly()->name('commerce:check-watches')->onOneServer();

// ---- Catalog module schedule ----
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Catalog\CleanupJob)->dailyAt('03:10')->name('catalog:cleanup')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Catalog\UpdateFxRateJob)->everySixHours()->name('catalog:fx')->onOneServer();
Illuminate\Support\Facades\Schedule::call(fn () => app(App\Services\Catalog\BudgetGuard::class)->check())->hourly()->name('catalog:budget')->onOneServer();
Illuminate\Support\Facades\Schedule::call(function () {
    // Only marketplaces with live_fetch reach a provider; the jobs re-check this when they run.
    foreach (App\Models\Marketplace::query()->where('enabled', true)->where('live_fetch', true)->pluck('id') as $market) {
        dispatch(new App\Jobs\Catalog\SeedBestsellersJob($market));
    }
})->weeklyOn(1, '04:20')->name('catalog:seed-bestsellers')->onOneServer();
Illuminate\Support\Facades\Schedule::call(function () {
    foreach (App\Models\Marketplace::query()->where('enabled', true)->where('live_fetch', true)->pluck('id') as $market) {
        dispatch(new App\Jobs\Catalog\IngestDealsJob($market));
    }
})->cron('35 */6 * * *')->name('catalog:ingest-deals')->onOneServer();
// ---- end catalog module schedule ----

// ---- Messaging module schedule (times are app timezone; marketing is held back in quiet hours by the Messenger) ----
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Messaging\SendCartReminders)->everyFifteenMinutes()->name('messaging:cart-reminders')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Messaging\SendPaymentReminders)->everyFiveMinutes()->name('messaging:payment-reminders')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Messaging\SendReviewRequests)->hourlyAt(20)->name('messaging:review-requests')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Messaging\SendPointsExpiryReminders)->dailyAt('04:30')->name('messaging:points-expiring')->onOneServer(); // 10:30 Dhaka when app TZ is UTC
Illuminate\Support\Facades\Schedule::command('retail:sms-balance --alert')->everySixHours()->name('messaging:sms-balance')->onOneServer();
// ---- end messaging module schedule ----

// ---- Money module schedule (wallet, gift cards, Skydrop Plus) ----
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Money\CreditMembershipWallets)->dailyAt('03:40')->name('money:plus-credits')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Money\MoneyHousekeeping)->dailyAt('04:05')->name('money:housekeeping')->onOneServer(); // 10:05 Dhaka
// ---- end money module schedule ----

// ---- Shopping module schedule ----
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Shopping\CloseDueGroups)->everyFiveMinutes()->name('shopping:close-groups')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Shopping\ProcessPreorders)->hourlyAt(25)->name('shopping:preorders')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Shopping\MatchProducts)->dailyAt('03:40')->name('shopping:match-products')->onOneServer();
// ---- end shopping module schedule ----

// ---- Fulfilment module schedule ----
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Fulfilment\CheckSupplierPrices)->cron('10 */2 * * *')->name('fulfilment:supplier-prices')->onOneServer();
Illuminate\Support\Facades\Schedule::job(new App\Jobs\Fulfilment\AutoApproveUnboxing)->hourlyAt(25)->name('fulfilment:unboxing-auto-approve')->onOneServer();
// ---- end fulfilment module schedule ----
