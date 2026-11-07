<?php

// Growth module: affiliate program, refer-a-friend and sharing. Every key can be overridden at runtime from the
// admin "Growth settings" page (settings table, same dotted key, e.g. "affiliate.cookie_days").
return [
    'affiliate' => [
        // Commission percent per product category ("affiliate.rates.{category}"); "default" covers the rest.
        'rates' => ['default' => 3.0],
        'cookie_days' => 30,          // how long a ?ref= link is remembered (last click wins)
        'hold_days' => 7,             // days after delivery before a commission is approved (return window + buffer)
        'min_payout_bdt' => 500,      // smallest payout an affiliate can request
        'max_per_order_bdt' => 1000,  // cap on the commission of a single order
    ],

    'referral' => [
        'inviter_points' => 200,      // to the inviter when the friend's first order is delivered
        'friend_points' => 100,       // to the friend at the same moment
    ],
];
