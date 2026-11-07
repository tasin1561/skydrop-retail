<?php

use App\Jobs\Catalog\CleanupJob;
use App\Jobs\Catalog\IngestDealsJob;
use App\Jobs\Catalog\SeedBestsellersJob;
use App\Jobs\Catalog\UpdateFxRateJob;
use App\Jobs\Commerce\CheckProductWatches;
use App\Jobs\Commerce\ExpirePoints;
use App\Jobs\Fulfilment\AutoApproveUnboxing;
use App\Jobs\Fulfilment\CheckSupplierPrices;
use App\Jobs\Messaging\SendCartReminders;
use App\Jobs\Messaging\SendPaymentReminders;
use App\Jobs\Messaging\SendPointsExpiryReminders;
use App\Jobs\Messaging\SendReviewRequests;
use App\Jobs\Money\CreditMembershipWallets;
use App\Jobs\Money\MoneyHousekeeping;
use App\Jobs\Shopping\CloseDueGroups;
use App\Jobs\Shopping\MatchProducts;
use App\Jobs\Shopping\ProcessPreorders;
use App\Models\Marketplace;
use App\Services\Catalog\BudgetGuard;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---- Commerce module schedule ----
Schedule::job(new ExpirePoints)->dailyAt('02:15')->name('commerce:expire-points')->onOneServer();
Schedule::job(new CheckProductWatches)->hourly()->name('commerce:check-watches')->onOneServer();

// ---- Catalog module schedule ----
Schedule::job(new CleanupJob)->dailyAt('03:10')->name('catalog:cleanup')->onOneServer();
Schedule::job(new UpdateFxRateJob)->everySixHours()->name('catalog:fx')->onOneServer();
Schedule::call(fn () => app(BudgetGuard::class)->check())->hourly()->name('catalog:budget')->onOneServer();
Schedule::call(function () {
    // Only marketplaces with live_fetch reach a provider; the jobs re-check this when they run.
    foreach (Marketplace::query()->where('enabled', true)->where('live_fetch', true)->pluck('id') as $market) {
        dispatch(new SeedBestsellersJob($market));
    }
})->weeklyOn(1, '04:20')->name('catalog:seed-bestsellers')->onOneServer();
Schedule::call(function () {
    foreach (Marketplace::query()->where('enabled', true)->where('live_fetch', true)->pluck('id') as $market) {
        dispatch(new IngestDealsJob($market));
    }
})->cron('35 */6 * * *')->name('catalog:ingest-deals')->onOneServer();
// ---- end catalog module schedule ----

// ---- Messaging module schedule (times are app timezone; marketing is held back in quiet hours by the Messenger) ----
Schedule::job(new SendCartReminders)->everyFifteenMinutes()->name('messaging:cart-reminders')->onOneServer();
Schedule::job(new SendPaymentReminders)->everyFiveMinutes()->name('messaging:payment-reminders')->onOneServer();
Schedule::job(new SendReviewRequests)->hourlyAt(20)->name('messaging:review-requests')->onOneServer();
Schedule::job(new SendPointsExpiryReminders)->dailyAt('04:30')->name('messaging:points-expiring')->onOneServer(); // 10:30 Dhaka when app TZ is UTC
Schedule::command('retail:sms-balance --alert')->everySixHours()->name('messaging:sms-balance')->onOneServer();
// ---- end messaging module schedule ----

// ---- Money module schedule (wallet, gift cards, Skydrop Plus) ----
Schedule::job(new CreditMembershipWallets)->dailyAt('03:40')->name('money:plus-credits')->onOneServer();
Schedule::job(new MoneyHousekeeping)->dailyAt('04:05')->name('money:housekeeping')->onOneServer(); // 10:05 Dhaka
// ---- end money module schedule ----

// ---- Shopping module schedule ----
Schedule::job(new CloseDueGroups)->everyFiveMinutes()->name('shopping:close-groups')->onOneServer();
Schedule::job(new ProcessPreorders)->hourlyAt(25)->name('shopping:preorders')->onOneServer();
Schedule::job(new MatchProducts)->dailyAt('03:40')->name('shopping:match-products')->onOneServer();
// ---- end shopping module schedule ----

// ---- Fulfilment module schedule ----
Schedule::job(new CheckSupplierPrices)->cron('10 */2 * * *')->name('fulfilment:supplier-prices')->onOneServer();
Schedule::job(new AutoApproveUnboxing)->hourlyAt(25)->name('fulfilment:unboxing-auto-approve')->onOneServer();
// ---- end fulfilment module schedule ----
