<?php

// Feature-switch driven endpoints (Admin → System → Features). See App\Http\Middleware\EnforceFeatureSwitches.

use App\Services\Money\EmiService;
use App\Support\Features;
use Illuminate\Support\Facades\Route;

// Payment methods and checkout extras the shopper can use right now.
Route::get('checkout/options', function () {
    $methods = [
        ['id' => 'bkash', 'label' => 'bKash', 'feature' => 'pay.bkash'],
        ['id' => 'nagad', 'label' => 'Nagad', 'feature' => 'pay.nagad'],
        ['id' => 'card', 'label' => 'Card (SSLCommerz)', 'feature' => 'pay.card'],
        ['id' => 'advance50', 'label' => '50% advance, rest on delivery', 'feature' => 'pay.advance50'],
        ['id' => 'card_emi', 'label' => 'Card EMI', 'feature' => 'pay.emi'], // POST /orders payment id (money module, GET /emi for tenures)
    ];

    return [
        'checkout' => Features::enabled('checkout'),
        'payments' => array_values(array_map(fn ($m) => ['id' => $m['id'], 'label' => $m['label']], array_filter($methods, fn ($m) => Features::enabled($m['feature'])))),
        'coupons' => Features::enabled('coupons'),
        'points' => Features::enabled('points'),
        'wallet' => Features::enabled('wallet'),
        'giftCards' => Features::enabled('gift_cards'),
        'membership' => Features::enabled('membership'),
        'emi' => app(EmiService::class)->config(), // money module: enabled, minOrderBdt, tenures, banks
    ];
});
