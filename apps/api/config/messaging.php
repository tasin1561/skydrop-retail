<?php

// Messaging module (email, SMS, push, in-app, reminders). Values marked "admin" can be overridden on
// Admin → System → Messaging (settings table, key "messaging.<path>").
return [
    'brand' => [
        'name' => env('MESSAGING_BRAND', 'Skydrop Retail'),
        'support_email' => env('MAIL_REPLY_TO_ADDRESS', 'support@skydrop.online'),
        'support_phone' => env('SUPPORT_PHONE', ''),
        'address' => env('SUPPORT_ADDRESS', 'Skydrop Retail, Dhaka, Bangladesh'),
        'logo_url' => env('MAIL_LOGO_URL'), // absolute URL; defaults to APP_URL/images/skydrop-retail-logo.png
    ],

    'mail' => [
        'reply_to' => env('MAIL_REPLY_TO_ADDRESS', 'support@skydrop.online'), // (admin)
        'reply_to_name' => env('MAIL_REPLY_TO_NAME', 'Skydrop Retail Support'),
        'invoice_link_days' => 30,
    ],

    'sms' => [
        // Ordered list of drivers to try. Empty = only retail.sms.driver (SMS_DRIVER). (admin)
        'failover' => array_values(array_filter(array_map('trim', explode(',', (string) env('SMS_FAILOVER', ''))))),
        'per_phone_per_hour' => (int) env('SMS_PER_PHONE_PER_HOUR', 8), // non-OTP messages
        'cost_per_segment_bdt' => (float) env('SMS_COST_PER_SEGMENT', 0.35), // estimate for broadcasts (admin)
        'low_balance_bdt' => (float) env('SMS_LOW_BALANCE', 200), // (admin)
        'bulksmsbd' => [
            'url' => env('BULKSMSBD_URL', 'https://bulksmsbd.net/api'),
            'api_key' => env('BULKSMSBD_API_KEY'),   // (admin)
            'sender_id' => env('BULKSMSBD_SENDER_ID'), // approved sender id / masking (admin)
            'timeout' => 10,
        ],
        'sslwireless' => [
            'url' => env('SSLWIRELESS_URL', 'https://smsplus.sslwireless.com/api/v3'),
            'api_token' => env('SSLWIRELESS_API_TOKEN', env('SMS_HTTP_TOKEN')),
            'sid' => env('SSLWIRELESS_SID', env('SMS_HTTP_SENDER_ID')),
            'timeout' => 10,
        ],
        'alpha' => [
            'url' => env('ALPHA_SMS_URL', 'https://api.sms.net.bd'),
            'api_key' => env('ALPHA_SMS_API_KEY'),
            'sender_id' => env('ALPHA_SMS_SENDER_ID'),
            'timeout' => 10,
        ],
    ],

    'push' => [
        'expo_url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
        'expo_access_token' => env('EXPO_ACCESS_TOKEN'), // optional (enhanced push security)
        // Web Push needs minishlink/web-push (not installed). Keys are documented for when it is added.
        'vapid_public_key' => env('VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
        'vapid_subject' => env('VAPID_SUBJECT', 'mailto:support@skydrop.online'),
    ],

    'queue' => [
        'otp' => env('MESSAGING_QUEUE_OTP', 'otp'),
        'transactional' => env('MESSAGING_QUEUE', 'notifications'),
        'marketing' => env('MESSAGING_QUEUE_MARKETING', 'marketing'),
    ],

    // Reminder timings (admin)
    'reminders' => [
        'cart_push_after_minutes' => 60,
        'cart_followup_after_hours' => 24,
        'cart_max_age_days' => 7,
        'payment_after_minutes' => 30,
        'review_after_days' => 3,
        'points_expiring_days' => 14,
    ],

    // No marketing messages in this window (Asia/Dhaka). Marketing queued inside it is delayed to the end. (admin)
    'quiet_hours' => ['start' => '22:00', 'end' => '09:00', 'timezone' => 'Asia/Dhaka'],
];
