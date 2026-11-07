<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderTrackingObserver;
use Illuminate\Support\ServiceProvider;

// Marketing tracking module wiring (kept apart from the catalog/commerce providers).
class TrackingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Order::observe(OrderTrackingObserver::class);
    }
}
