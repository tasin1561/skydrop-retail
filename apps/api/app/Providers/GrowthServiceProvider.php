<?php

namespace App\Providers;

use App\Jobs\Growth\ApproveAffiliateCommissions;
use App\Models\Order;
use App\Observers\GrowthOrderObserver;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

// Growth module wiring: affiliate + referral hooks on order status, and the commission approval schedule.
class GrowthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Order::observe(GrowthOrderObserver::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new ApproveAffiliateCommissions)->hourly()->name('growth:approve-commissions')->withoutOverlapping();
        });
    }
}
