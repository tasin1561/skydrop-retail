<?php

// Fulfilment operations defaults. Every key can be overridden from Admin → Hub → Fulfilment settings
// (settings table, key "fulfilment.{key}"). See App\Services\Fulfilment\FulfilmentSettings.
return [
    'hub' => [
        'name' => 'Skydrop Kolkata hub',
        'pincode' => env('RETAIL_PINCODE', '700001'),
        // Flag an order when the measured weight exceeds the estimate by more than this percentage.
        'weight_variance_pct' => 15,
    ],
    'unboxing' => [
        'fee_bdt' => 150,
        'per' => 'shipment', // shipment (one per store group) | item (per unit)
        'auto_approve_hours' => 48,
    ],
    'returns' => [
        'window_days' => 7,
        'changed_mind' => false,
    ],
    'price_alerts' => [
        'rise_pct' => 5, // alert when the source INR price rises by more than this
    ],
    'tracking' => [
        'rider_fresh_minutes' => 10,
        // Remaining days to delivery from each stage (ETA shown on the tracking map).
        'eta_days' => ['placed' => [5, 9], 'verified' => [5, 8], 'purchased' => [4, 7], 'india_hub' => [3, 5],
            'in_transit' => [2, 4], 'dhaka_hub' => [1, 2], 'out_for_delivery' => [0, 0]],
    ],
];
