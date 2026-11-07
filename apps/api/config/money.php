<?php

// Money module defaults (wallet, gift cards, Skydrop Plus, card EMI). Every key can be overridden at runtime
// from Admin → Payments → Wallet, gift cards & EMI (settings table key "money.{key}", see App\Services\Money\MoneySettings).
return [
    'wallet' => [
        'refund_default' => 'wallet', // wallet | original: preselected destination for refunds (cancel + admin refund)
        'refund_bonus_pct' => 0,       // extra % credited when a refund goes to the wallet (e.g. 2 = +2%)
    ],

    'gift_cards' => [
        'presets' => [500, 1000, 2000, 5000],
        'min_bdt' => 200,
        'max_bdt' => 50000,
        'expiry_months' => 12,
        'designs' => [
            'classic' => 'Skydrop classic',
            'eid' => 'Eid Mubarak',
            'birthday' => 'Happy birthday',
            'thanks' => 'Thank you',
            'wedding' => 'Wedding',
        ],
    ],

    'membership' => [
        'reminder_days' => 3,        // renewal reminder this many days before renews_at
        'credit_expiry_days' => 45,  // monthly wallet credit expires after this many days (0 = never)
    ],

    'emi' => [
        'enabled' => true, // also needs the "pay.emi" feature switch
        'min_order_bdt' => 5000,
        'tenures' => [3, 6, 9, 12],
        // Customer-paid charge in % of the financed amount, per bank and tenure (SSLCommerz EMI banks).
        'banks' => [
            ['code' => 'brac', 'name' => 'BRAC Bank', 'rates' => ['3' => 3, '6' => 4.5, '9' => 6.5, '12' => 8]],
            ['code' => 'city', 'name' => 'The City Bank (Amex)', 'rates' => ['3' => 3, '6' => 5, '9' => 7, '12' => 9]],
            ['code' => 'ebl', 'name' => 'Eastern Bank', 'rates' => ['3' => 0, '6' => 4.5, '9' => 6.5, '12' => 8.5]],
            ['code' => 'dbbl', 'name' => 'Dutch-Bangla Bank', 'rates' => ['3' => 3.5, '6' => 5, '9' => 7, '12' => 9]],
        ],
    ],
];
