<?php

use App\Providers\AppServiceProvider;
use App\Providers\CategoryServiceProvider;
use App\Providers\CommerceServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\GrowthServiceProvider;
use App\Providers\MessagingServiceProvider;
use App\Providers\MoneyServiceProvider;
use App\Providers\OpsServiceProvider;
use App\Providers\RbacServiceProvider;
use App\Providers\ShoppingServiceProvider;
use App\Providers\SystemServiceProvider;
use App\Providers\TrackingServiceProvider;

return [
    AppServiceProvider::class,
    CommerceServiceProvider::class,
    GrowthServiceProvider::class,
    TrackingServiceProvider::class,
    MessagingServiceProvider::class,
    MoneyServiceProvider::class,
    RbacServiceProvider::class,
    OpsServiceProvider::class,
    CategoryServiceProvider::class,
    ShoppingServiceProvider::class,
    SystemServiceProvider::class,
    AdminPanelProvider::class,
];
