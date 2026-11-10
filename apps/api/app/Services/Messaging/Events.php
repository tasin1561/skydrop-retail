<?php

namespace App\Services\Messaging;

/**
 * Every message the platform sends, with its category, channels, in-app icon and default templates (en + bn).
 *
 * Categories: transactional (always sent), alert (the shopper asked for it: price drop / back in stock; always sent),
 * marketing (respects users.notify_prefs and quiet hours), otp (SMS only, sync, never marketing routes).
 *
 * Templates use {{placeholder}} and {{#flag}}shown when flag is truthy{{/flag}} / {{^flag}}shown when falsy{{/flag}}.
 * SMS bodies stay short: aim for 1 segment in English (160) and at most 2 in Bangla (134).
 * Defaults with 'off' => true are seeded inactive (e.g. SMS for minor tracking steps, to save SMS cost).
 */
final class Events
{
    public const ICONS = [
        'welcome' => 'spark', 'order' => 'receipt', 'payment' => 'wallet', 'payment_failed' => 'card', 'truck' => 'truck',
        'plane' => 'plane', 'check' => 'check', 'package' => 'package', 'cancel' => 'x', 'star' => 'star', 'points' => 'tag',
        'price' => 'tag', 'stock' => 'bolt', 'cart' => 'cart', 'info' => 'info', 'megaphone' => 'bolt',
    ];

    /** @return array<string, array{category: string, icon: string, label: string, sample: array<string, mixed>}> */
    public static function all(): array
    {
        return array_map(fn ($e) => array_diff_key($e, ['t' => 1]), self::defs());
    }

    public static function get(string $event): ?array
    {
        return self::all()[$event] ?? null;
    }

    public static function labels(): array
    {
        return array_map(fn ($e) => $e['label'], self::all());
    }

    /** Order status → event key. */
    public static function forStatus(string $status): ?string
    {
        return match ($status) {
            'verified', 'purchased', 'india_hub', 'in_transit', 'dhaka_hub', 'out_for_delivery', 'delivered', 'cancelled' => 'order_'.$status,
            default => null,
        };
    }

    /**
     * Default templates as rows for message_templates.
     *
     * @return list<array{key: string, channel: string, locale: string, subject: ?string, body: string, active: bool}>
     */
    public static function defaultTemplates(): array
    {
        $rows = [];
        foreach (self::defs() as $key => $def) {
            foreach ($def['t'] as $locale => $channels) {
                foreach ($channels as $channel => $t) {
                    $rows[] = ['key' => $key, 'channel' => $channel, 'locale' => $locale, 'subject' => $t[1] ?? null, 'body' => $t[0], 'active' => ! ($t['off'] ?? false)];
                }
            }
        }

        return $rows;
    }

    /** Default template for one key/channel/locale (fallback when the table has no row). */
    public static function defaultTemplate(string $key, string $channel, string $locale): ?array
    {
        $t = self::defs()[$key]['t'][$locale][$channel] ?? null;

        return $t ? ['subject' => $t[1] ?? null, 'body' => $t[0], 'active' => ! ($t['off'] ?? false)] : null;
    }

    private static function defs(): array
    {
        $order = ['name' => 'Rahim', 'order_id' => 'SR-24117-AB12', 'total' => 'BDT 12,480', 'link' => 'https://retail.skydrop.online/orders/SR-24117-AB12'];

        return [
            'otp' => [
                'category' => 'otp', 'icon' => 'info', 'label' => 'Sign-in code (OTP)',
                'sample' => ['code' => '482913', 'minutes' => 5],
                't' => [
                    'en' => ['sms' => ['Your Skydrop Retail code is {{code}}. It expires in {{minutes}} minutes. Never share it.']],
                    'bn' => ['sms' => ['আপনার Skydrop Retail কোড {{code}}। {{minutes}} মিনিটে মেয়াদ শেষ হবে। কাউকে জানাবেন না।']],
                ],
            ],
            'email_verify' => [
                'category' => 'transactional', 'icon' => 'check', 'label' => 'Verify email address',
                'sample' => ['name' => 'Rahim', 'link' => 'https://api.example.com/verify'],
                't' => [
                    'en' => ['email' => ["Hi {{name}},\n\nPlease confirm this email address for your Skydrop Retail account. We will send order updates and invoices here.\n\nThe link expires in 3 days. If you did not add this email, you can ignore this message.", 'Confirm your email address']],
                    'bn' => ['email' => ["প্রিয় {{name}},\n\nআপনার Skydrop Retail অ্যাকাউন্টের জন্য এই ইমেইলটি নিশ্চিত করুন। অর্ডারের আপডেট ও ইনভয়েস এখানে পাঠানো হবে।\n\nলিংকটি ৩ দিন কার্যকর থাকবে। আপনি এই ইমেইল যোগ না করে থাকলে বার্তাটি উপেক্ষা করুন।", 'আপনার ইমেইল নিশ্চিত করুন']],
                ],
            ],
            'welcome' => [
                'category' => 'transactional', 'icon' => 'welcome', 'label' => 'Welcome',
                'sample' => ['name' => 'Rahim', 'points' => 100, 'link' => 'https://retail.skydrop.online'],
                't' => [
                    'en' => [
                        'sms' => ['Welcome to Skydrop Retail, {{name}}! Shop Amazon, Flipkart & Myntra India, delivered to your door. {{#points}}You have {{points}} bonus points.{{/points}}', 'off' => true],
                        'email' => ["Hi {{name}},\n\nWelcome to Skydrop Retail. We buy from Amazon India, Flipkart and Myntra for you, and Skydrop delivers to your door anywhere in Bangladesh.\n\n{{#points}}Your account starts with {{points}} Skydrop Points. Use them on your first order.{{/points}}\n\nPrices are shown in taka and include shipping estimates, so there are no surprises at the door.", 'Welcome to Skydrop Retail'],
                        'push' => ['{{#points}}{{points}} bonus points are waiting. {{/points}}Start shopping Indian stores in taka.', 'Welcome, {{name}}!'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail-এ স্বাগতম, {{name}}! ভারতের Amazon, Flipkart ও Myntra থেকে কিনুন, বাসায় ডেলিভারি। {{#points}}আপনার {{points}} বোনাস পয়েন্ট আছে।{{/points}}', 'off' => true],
                        'email' => ["প্রিয় {{name}},\n\nSkydrop Retail-এ স্বাগতম। আমরা আপনার হয়ে Amazon India, Flipkart ও Myntra থেকে কিনি, আর Skydrop বাংলাদেশের যেকোনো জায়গায় আপনার দরজায় পৌঁছে দেয়।\n\n{{#points}}আপনার অ্যাকাউন্টে {{points}} Skydrop পয়েন্ট যোগ হয়েছে। প্রথম অর্ডারে ব্যবহার করুন।{{/points}}\n\nসব দাম টাকায় দেখানো হয়, শিপিং খরচসহ।", 'Skydrop Retail-এ স্বাগতম'],
                        'push' => ['{{#points}}{{points}} বোনাস পয়েন্ট অপেক্ষা করছে। {{/points}}টাকায় ভারতের স্টোর থেকে কেনাকাটা শুরু করুন।', 'স্বাগতম, {{name}}!'],
                    ],
                ],
            ],
            'order_placed' => [
                'category' => 'transactional', 'icon' => 'order', 'label' => 'Order placed',
                'sample' => $order + ['points_to_earn' => 124, 'items_count' => 3],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: order {{order_id}} placed. Total {{total}}. Track: {{link}}'],
                        'email' => ["Hi {{name}},\n\nThanks for your order. We have received order {{order_id}} and will verify the live price and stock with the store in India before buying.\n\n{{#points_to_earn}}You will earn {{points_to_earn}} Skydrop Points when it is delivered.{{/points_to_earn}}", 'Order {{order_id}} placed'],
                        'push' => ['Total {{total}}. We will verify price and stock, then buy it in India.', 'Order {{order_id}} placed'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: অর্ডার {{order_id}} গ্রহণ করা হয়েছে। মোট {{total}}। ট্র্যাক: {{link}}'],
                        'email' => ["প্রিয় {{name}},\n\nঅর্ডারের জন্য ধন্যবাদ। আপনার অর্ডার {{order_id}} পেয়েছি। কেনার আগে ভারতের স্টোরে দাম ও স্টক যাচাই করা হবে।\n\n{{#points_to_earn}}ডেলিভারির পর আপনি {{points_to_earn}} Skydrop পয়েন্ট পাবেন।{{/points_to_earn}}", 'অর্ডার {{order_id}} গ্রহণ করা হয়েছে'],
                        'push' => ['মোট {{total}}। দাম ও স্টক যাচাই করে ভারতে কেনা হবে।', 'অর্ডার {{order_id}} গ্রহণ করা হয়েছে'],
                    ],
                ],
            ],
            'payment_received' => [
                'category' => 'transactional', 'icon' => 'payment', 'label' => 'Payment received',
                'sample' => $order + ['amount' => 'BDT 6,240', 'due' => 'BDT 6,240'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: we received {{amount}} for order {{order_id}}.{{#due}} Pay {{due}} to the Skydrop rider on delivery.{{/due}}'],
                        'email' => ["Hi {{name}},\n\nWe received your payment of {{amount}} for order {{order_id}}.\n\n{{#due}}The remaining {{due}} is paid in cash to the Skydrop rider on delivery.{{/due}}{{^due}}Your order is fully paid.{{/due}}", 'Payment received for {{order_id}}'],
                        'push' => ['{{amount}} received for {{order_id}}.{{#due}} {{due}} is due on delivery.{{/due}}', 'Payment received'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: অর্ডার {{order_id}}-এর জন্য {{amount}} পেয়েছি।{{#due}} ডেলিভারির সময় রাইডারকে {{due}} দিন।{{/due}}'],
                        'email' => ["প্রিয় {{name}},\n\nঅর্ডার {{order_id}}-এর জন্য আপনার {{amount}} পেমেন্ট পেয়েছি।\n\n{{#due}}বাকি {{due}} ডেলিভারির সময় Skydrop রাইডারকে নগদে দেবেন।{{/due}}{{^due}}আপনার অর্ডারের পুরো মূল্য পরিশোধিত।{{/due}}", '{{order_id}}-এর পেমেন্ট পেয়েছি'],
                        'push' => ['{{order_id}}-এর জন্য {{amount}} পেয়েছি।{{#due}} ডেলিভারিতে {{due}} দিতে হবে।{{/due}}', 'পেমেন্ট পেয়েছি'],
                    ],
                ],
            ],
            'digital_payment_received' => [
                'category' => 'transactional', 'icon' => 'payment', 'label' => 'Gift card / Skydrop Plus payment received',
                'sample' => ['name' => 'Rahim', 'order_id' => 'SR-24117-GC01', 'amount' => 'BDT 2,000', 'total' => 'BDT 2,000', 'gift_card' => true,
                    'plus' => false, 'plan' => null, 'link' => 'https://retail.skydrop.online/gift-cards'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: we received {{amount}} for your {{#gift_card}}gift card. The code is on its way to the recipient.{{/gift_card}}{{#plus}}Skydrop Plus{{#plan}} ({{plan}}){{/plan}}. Your benefits are active.{{/plus}}'],
                        'email' => ["Hi {{name}},\n\nWe received your payment of {{amount}} ({{order_id}}).\n\n{{#gift_card}}Your Skydrop gift card is now active and the code has been sent to the recipient. You can see it any time under Gift cards.{{/gift_card}}{{#plus}}Your Skydrop Plus membership{{#plan}} ({{plan}}){{/plan}} is active. Your member benefits apply from your next order.{{/plus}}", '{{#gift_card}}Gift card payment received{{/gift_card}}{{#plus}}Skydrop Plus payment received{{/plus}}'],
                        'push' => ['{{amount}} received. {{#gift_card}}Your gift card is active.{{/gift_card}}{{#plus}}Your Skydrop Plus benefits are active.{{/plus}}', 'Payment received'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: {{amount}} পেয়েছি। {{#gift_card}}গিফট কার্ড চালু হয়েছে, কোড প্রাপকের কাছে পাঠানো হচ্ছে।{{/gift_card}}{{#plus}}Skydrop Plus{{#plan}} ({{plan}}){{/plan}} চালু হয়েছে।{{/plus}}'],
                        'email' => ["প্রিয় {{name}},\n\nআপনার {{amount}} পেমেন্ট পেয়েছি ({{order_id}})।\n\n{{#gift_card}}আপনার Skydrop গিফট কার্ড চালু হয়েছে এবং কোড প্রাপকের কাছে পাঠানো হয়েছে। গিফট কার্ড পেজে যেকোনো সময় দেখতে পারবেন।{{/gift_card}}{{#plus}}আপনার Skydrop Plus সদস্যপদ{{#plan}} ({{plan}}){{/plan}} চালু হয়েছে। পরের অর্ডার থেকে সুবিধাগুলো পাবেন।{{/plus}}", 'পেমেন্ট পেয়েছি'],
                        'push' => ['{{amount}} পেয়েছি। {{#gift_card}}গিফট কার্ড চালু হয়েছে।{{/gift_card}}{{#plus}}Skydrop Plus সুবিধা চালু হয়েছে।{{/plus}}', 'পেমেন্ট পেয়েছি'],
                    ],
                ],
            ],
            'payment_failed' => [
                'category' => 'transactional', 'icon' => 'payment_failed', 'label' => 'Payment failed',
                'sample' => $order + ['amount' => 'BDT 12,480', 'pay_link' => 'https://retail.skydrop.online/orders/SR-24117-AB12?pay=1'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: payment for order {{order_id}} did not go through. Try again: {{pay_link}}'],
                        'email' => ["Hi {{name}},\n\nYour payment of {{amount}} for order {{order_id}} did not go through. No money was taken for this attempt.\n\nYour order is saved. You can try again with bKash, Nagad or card.", 'Payment failed for {{order_id}}'],
                        'push' => ['Your order is saved. Tap to try paying again.', 'Payment did not go through'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: অর্ডার {{order_id}}-এর পেমেন্ট সফল হয়নি। আবার চেষ্টা করুন: {{pay_link}}'],
                        'email' => ["প্রিয় {{name}},\n\nঅর্ডার {{order_id}}-এর {{amount}} পেমেন্ট সফল হয়নি। এই চেষ্টায় কোনো টাকা কাটা হয়নি।\n\nআপনার অর্ডার সংরক্ষিত আছে। bKash, Nagad বা কার্ড দিয়ে আবার চেষ্টা করুন।", '{{order_id}}-এর পেমেন্ট সফল হয়নি'],
                        'push' => ['অর্ডার সংরক্ষিত আছে। আবার পেমেন্ট করতে ট্যাপ করুন।', 'পেমেন্ট সফল হয়নি'],
                    ],
                ],
            ],
            'payment_pending' => [
                'category' => 'transactional', 'icon' => 'payment', 'label' => 'Payment reminder (30 min)',
                'sample' => $order + ['amount' => 'BDT 12,480', 'pay_link' => 'https://retail.skydrop.online/orders/SR-24117-AB12?pay=1'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: order {{order_id}} is waiting for payment of {{amount}}. Pay now to lock the price: {{pay_link}}'],
                        'email' => ["Hi {{name}},\n\nOrder {{order_id}} is waiting for your payment of {{amount}}. Indian store prices change often, so pay soon to keep this price.", 'Complete payment for {{order_id}}'],
                        'push' => ['Pay {{amount}} now to keep this price.', 'Order {{order_id}} is waiting'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: অর্ডার {{order_id}}-এর {{amount}} পেমেন্ট বাকি। পে করুন: {{pay_link}}'],
                        'email' => ["প্রিয় {{name}},\n\nঅর্ডার {{order_id}}-এর {{amount}} পেমেন্ট এখনও বাকি। ভারতের স্টোরে দাম প্রায়ই বদলায়, তাই এই দাম পেতে দ্রুত পেমেন্ট করুন।", '{{order_id}}-এর পেমেন্ট সম্পন্ন করুন'],
                        'push' => ['এই দাম ধরে রাখতে এখনই {{amount}} পে করুন।', 'অর্ডার {{order_id}} অপেক্ষায়'],
                    ],
                ],
            ],
            'order_verified' => self::status('Verified', 'check', 'Price and stock verified', 'দাম ও স্টক যাচাই সম্পন্ন',
                'We checked the live price and stock with the store. We will buy your items shortly.',
                'স্টোরে দাম ও স্টক যাচাই করা হয়েছে। শিগগিরই আপনার পণ্য কেনা হবে।', smsOff: true),
            'order_purchased' => self::status('Bought in India', 'package', 'Bought in India', 'ভারতে কেনা হয়েছে',
                'Your items are bought in India and heading to our Kolkata hub.',
                'আপনার পণ্য ভারতে কেনা হয়েছে, কলকাতা হাবে যাচ্ছে।'),
            'order_india_hub' => self::status('At Kolkata hub', 'package', 'At our Kolkata hub', 'কলকাতা হাবে পৌঁছেছে',
                'Your items reached the Skydrop Kolkata hub and are being packed for the border.',
                'আপনার পণ্য Skydrop কলকাতা হাবে পৌঁছেছে, বর্ডারের জন্য প্যাক করা হচ্ছে।', smsOff: true),
            'order_in_transit' => self::status('Crossing border', 'plane', 'On the way to Bangladesh', 'বাংলাদেশের পথে',
                'Your parcel is crossing the border to Bangladesh.',
                'আপনার পার্সেল বর্ডার পার হয়ে বাংলাদেশে আসছে।', smsOff: true),
            'order_dhaka_hub' => self::status('At Dhaka hub', 'package', 'At our Dhaka hub', 'ঢাকা হাবে পৌঁছেছে',
                'Your parcel reached the Skydrop Dhaka hub. A rider will be assigned soon.',
                'আপনার পার্সেল Skydrop ঢাকা হাবে পৌঁছেছে। শিগগিরই রাইডার দেওয়া হবে।', smsOff: true),
            'order_out_for_delivery' => [
                'category' => 'transactional', 'icon' => 'truck', 'label' => 'Out for delivery',
                'sample' => $order + ['due' => 'BDT 6,240'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: order {{order_id}} is out for delivery today.{{#due}} Please keep {{due}} ready for the rider.{{/due}}'],
                        'email' => ["Hi {{name}},\n\nGood news: order {{order_id}} is out for delivery today.\n\n{{#due}}This order was a 50% advance. Please keep {{due}} ready to pay the Skydrop rider.{{/due}}{{^due}}Your order is fully paid. Nothing to pay at the door.{{/due}}", 'Order {{order_id}} is out for delivery'],
                        'push' => ['Arriving today.{{#due}} Keep {{due}} ready for the rider.{{/due}}', 'Out for delivery'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: অর্ডার {{order_id}} আজ ডেলিভারিতে বের হয়েছে।{{#due}} রাইডারের জন্য {{due}} প্রস্তুত রাখুন।{{/due}}'],
                        'email' => ["প্রিয় {{name}},\n\nসুখবর: অর্ডার {{order_id}} আজ ডেলিভারিতে বের হয়েছে।\n\n{{#due}}এটি ৫০% অগ্রিম অর্ডার। Skydrop রাইডারকে দেওয়ার জন্য {{due}} প্রস্তুত রাখুন।{{/due}}{{^due}}আপনার অর্ডারের পুরো মূল্য পরিশোধিত। দরজায় কিছু দিতে হবে না।{{/due}}", 'অর্ডার {{order_id}} ডেলিভারিতে বের হয়েছে'],
                        'push' => ['আজ পৌঁছাবে।{{#due}} রাইডারের জন্য {{due}} প্রস্তুত রাখুন।{{/due}}', 'ডেলিভারিতে বের হয়েছে'],
                    ],
                ],
            ],
            'order_delivered' => [
                'category' => 'transactional', 'icon' => 'check', 'label' => 'Delivered (+ review request)',
                'sample' => $order + ['points_earned' => 124, 'review_link' => 'https://retail.skydrop.online/orders/SR-24117-AB12#review'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: order {{order_id}} delivered. Thank you! Need help? Reply within 3 days from your account.'],
                        'email' => ["Hi {{name}},\n\nOrder {{order_id}} has been delivered. Thank you for shopping with Skydrop Retail.\n\n{{#points_earned}}{{points_earned}} Skydrop Points have been added to your account.{{/points_earned}}\n\nHow was it? A quick review helps other shoppers in Bangladesh. If something is wrong, you can request help from your account within 3 days.", 'Order {{order_id}} delivered'],
                        'push' => ['{{#points_earned}}+{{points_earned}} points added. {{/points_earned}}Tap to rate your items.', 'Delivered!'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: অর্ডার {{order_id}} ডেলিভারি হয়েছে। ধন্যবাদ! সমস্যা হলে ৩ দিনের মধ্যে অ্যাকাউন্ট থেকে জানান।'],
                        'email' => ["প্রিয় {{name}},\n\nঅর্ডার {{order_id}} ডেলিভারি হয়েছে। Skydrop Retail-এ কেনাকাটার জন্য ধন্যবাদ।\n\n{{#points_earned}}আপনার অ্যাকাউন্টে {{points_earned}} Skydrop পয়েন্ট যোগ হয়েছে।{{/points_earned}}\n\nকেমন লাগল? একটি রিভিউ অন্য ক্রেতাদের সাহায্য করে। কোনো সমস্যা থাকলে ৩ দিনের মধ্যে অ্যাকাউন্ট থেকে জানান।", 'অর্ডার {{order_id}} ডেলিভারি হয়েছে'],
                        'push' => ['{{#points_earned}}+{{points_earned}} পয়েন্ট যোগ হয়েছে। {{/points_earned}}রিভিউ দিতে ট্যাপ করুন।', 'ডেলিভারি হয়েছে!'],
                    ],
                ],
            ],
            'order_cancelled' => [
                'category' => 'transactional', 'icon' => 'cancel', 'label' => 'Cancelled / refunded',
                'sample' => $order + ['refund' => 'BDT 12,480', 'refunded' => true],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: order {{order_id}} was cancelled.{{#refund}} Your refund of {{refund}} is on its way.{{/refund}}'],
                        'email' => ["Hi {{name}},\n\nOrder {{order_id}} was cancelled.\n\n{{#refund}}{{#refunded}}We refunded {{refund}} to your original payment method.{{/refunded}}{{^refunded}}Your refund of {{refund}} is on its way to your original payment method.{{/refunded}} It can take 3 to 7 working days to show up.{{/refund}}\n\nAny points used on this order have been returned to your account.", 'Order {{order_id}} cancelled'],
                        'push' => ['{{#refund}}Refund of {{refund}} is on its way.{{/refund}}{{^refund}}We are sorry to see it go.{{/refund}}', 'Order {{order_id}} cancelled'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: অর্ডার {{order_id}} বাতিল হয়েছে।{{#refund}} আপনার {{refund}} রিফান্ড প্রক্রিয়াধীন।{{/refund}}'],
                        'email' => ["প্রিয় {{name}},\n\nঅর্ডার {{order_id}} বাতিল হয়েছে।\n\n{{#refund}}{{#refunded}}{{refund}} আপনার পেমেন্ট মাধ্যমে ফেরত দেওয়া হয়েছে।{{/refunded}}{{^refunded}}আপনার {{refund}} রিফান্ড পেমেন্ট মাধ্যমে ফেরত যাচ্ছে।{{/refunded}} পৌঁছাতে ৩ থেকে ৭ কর্মদিবস লাগতে পারে।{{/refund}}\n\nএই অর্ডারে ব্যবহৃত পয়েন্ট আপনার অ্যাকাউন্টে ফেরত দেওয়া হয়েছে।", 'অর্ডার {{order_id}} বাতিল হয়েছে'],
                        'push' => ['{{#refund}}{{refund}} রিফান্ড প্রক্রিয়াধীন।{{/refund}}{{^refund}}অর্ডারটি বাতিল করা হয়েছে।{{/refund}}', 'অর্ডার {{order_id}} বাতিল'],
                    ],
                ],
            ],
            'review_request' => [
                'category' => 'transactional', 'icon' => 'star', 'label' => 'Review request (3 days after delivery)',
                'sample' => $order + ['product' => 'boAt Airdopes 141', 'review_link' => 'https://retail.skydrop.online/orders/SR-24117-AB12#review'],
                't' => [
                    'en' => [
                        'email' => ["Hi {{name}},\n\nHow are you finding {{product}}? Your review of order {{order_id}} helps other shoppers in Bangladesh buy with confidence. It takes less than a minute.", 'How was your order {{order_id}}?'],
                        'push' => ['Tell other shoppers what you think of {{product}}.', 'How was your order?'],
                    ],
                    'bn' => [
                        'email' => ["প্রিয় {{name}},\n\n{{product}} কেমন লাগছে? অর্ডার {{order_id}}-এর রিভিউ অন্য ক্রেতাদের নিশ্চিন্তে কিনতে সাহায্য করে। এক মিনিটও লাগবে না।", 'আপনার অর্ডার {{order_id}} কেমন ছিল?'],
                        'push' => ['{{product}} সম্পর্কে আপনার মতামত জানান।', 'অর্ডার কেমন ছিল?'],
                    ],
                ],
            ],
            'points_expiring' => [
                'category' => 'transactional', 'icon' => 'points', 'label' => 'Points expiring soon',
                'sample' => ['name' => 'Rahim', 'points' => 350, 'value' => 'BDT 150', 'expires_on' => '21 Nov 2026', 'link' => 'https://retail.skydrop.online/account/points'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: {{points}} points (worth {{value}}) expire on {{expires_on}}. Use them on your next order: {{link}}'],
                        'email' => ["Hi {{name}},\n\n{{points}} of your Skydrop Points (worth {{value}}) expire on {{expires_on}}. Use them at checkout before then.", 'Your {{points}} points expire on {{expires_on}}'],
                        'push' => ['Worth {{value}}. Use them before {{expires_on}}.', '{{points}} points expiring soon'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: আপনার {{points}} পয়েন্ট ({{value}}) {{expires_on}}-এ শেষ হবে। {{link}}'],
                        'email' => ["প্রিয় {{name}},\n\nআপনার {{points}} Skydrop পয়েন্ট ({{value}} মূল্যের) {{expires_on}} তারিখে মেয়াদোত্তীর্ণ হবে। তার আগেই চেকআউটে ব্যবহার করুন।", 'আপনার {{points}} পয়েন্ট {{expires_on}}-এ শেষ হবে'],
                        'push' => ['{{value}} মূল্যের। {{expires_on}}-এর আগে ব্যবহার করুন।', '{{points}} পয়েন্ট শিগগির শেষ হবে'],
                    ],
                ],
            ],
            'price_drop' => [
                'category' => 'alert', 'icon' => 'price', 'label' => 'Price drop alert',
                'sample' => ['name' => 'Rahim', 'title' => 'boAt Airdopes 141 Bluetooth Earbuds', 'price' => 'BDT 2,150', 'link' => 'https://retail.skydrop.online/p/B0TEST'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: price drop! {{title}} is now {{price}}. {{link}}'],
                        'email' => ["Hi {{name}},\n\nGood news: an item you are watching just got cheaper.\n\n{{title}} is now {{price}}. Prices on Indian stores change quickly, so grab it while it lasts.", 'Price drop: {{title}}'],
                        'push' => ['{{title}} is now {{price}}.', 'Price drop!'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: দাম কমেছে! {{title}} এখন {{price}}। {{link}}'],
                        'email' => ["প্রিয় {{name}},\n\nসুখবর: আপনার নজরে রাখা একটি পণ্যের দাম কমেছে।\n\n{{title}} এখন {{price}}। ভারতের স্টোরে দাম দ্রুত বদলায়, তাই এখনই নিন।", 'দাম কমেছে: {{title}}'],
                        'push' => ['{{title}} এখন {{price}}।', 'দাম কমেছে!'],
                    ],
                ],
            ],
            'back_in_stock' => [
                'category' => 'alert', 'icon' => 'stock', 'label' => 'Back in stock alert',
                'sample' => ['name' => 'Rahim', 'title' => 'boAt Airdopes 141 Bluetooth Earbuds', 'link' => 'https://retail.skydrop.online/p/B0TEST'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: back in stock: {{title}}. {{link}}'],
                        'email' => ["Hi {{name}},\n\n{{title}} is back in stock. Popular items sell out quickly, so order soon.", 'Back in stock: {{title}}'],
                        'push' => ['{{title}} is available again.', 'Back in stock'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: আবার স্টকে: {{title}}। {{link}}'],
                        'email' => ["প্রিয় {{name}},\n\n{{title}} আবার স্টকে এসেছে। জনপ্রিয় পণ্য দ্রুত শেষ হয়, তাই তাড়াতাড়ি অর্ডার করুন।", 'আবার স্টকে: {{title}}'],
                        'push' => ['{{title}} আবার পাওয়া যাচ্ছে।', 'আবার স্টকে'],
                    ],
                ],
            ],
            'cart_reminder_1h' => [
                'category' => 'marketing', 'icon' => 'cart', 'label' => 'Abandoned cart (1 hour, push)',
                'sample' => ['name' => 'Rahim', 'count' => 2, 'total' => 'BDT 4,300', 'title' => 'boAt Airdopes 141', 'link' => 'https://retail.skydrop.online/cart'],
                't' => [
                    'en' => ['push' => ['{{title}}{{#more}} and {{more}} more{{/more}} are waiting. Total {{total}}.', 'Still thinking it over?']],
                    'bn' => ['push' => ['{{title}}{{#more}} সহ আরও {{more}}টি{{/more}} পণ্য অপেক্ষা করছে। মোট {{total}}।', 'এখনো ভাবছেন?']],
                ],
            ],
            'cart_reminder_24h' => [
                'category' => 'marketing', 'icon' => 'cart', 'label' => 'Abandoned cart (24 hours, SMS/email)',
                'sample' => ['name' => 'Rahim', 'count' => 2, 'total' => 'BDT 4,300', 'title' => 'boAt Airdopes 141', 'link' => 'https://retail.skydrop.online/cart'],
                't' => [
                    'en' => [
                        'sms' => ['Skydrop Retail: your cart ({{count}} items, {{total}}) is saved. Prices in India change fast: {{link}}'],
                        'email' => ["Hi {{name}},\n\nYou left {{count}} item(s) in your cart. Indian store prices and stock change often, so we can't hold them for long.", 'Your cart is waiting'],
                    ],
                    'bn' => [
                        'sms' => ['Skydrop Retail: আপনার কার্টে {{count}}টি পণ্য ({{total}}) আছে। ভারতে দাম দ্রুত বদলায়: {{link}}'],
                        'email' => ["প্রিয় {{name}},\n\nআপনার কার্টে {{count}}টি পণ্য রয়ে গেছে। ভারতের স্টোরে দাম ও স্টক প্রায়ই বদলায়, তাই বেশিক্ষণ ধরে রাখা যায় না।", 'আপনার কার্ট অপেক্ষা করছে'],
                    ],
                ],
            ],
        ];
    }

    private static function status(string $label, string $icon, string $titleEn, string $titleBn, string $en, string $bn, bool $smsOff = false): array
    {
        return [
            'category' => 'transactional', 'icon' => $icon, 'label' => $label,
            'sample' => ['name' => 'Rahim', 'order_id' => 'SR-24117-AB12', 'link' => 'https://retail.skydrop.online/orders/SR-24117-AB12'],
            't' => [
                'en' => [
                    'sms' => ['Skydrop Retail: order {{order_id}}: '.$en.' Track: {{link}}', 'off' => $smsOff],
                    'email' => ["Hi {{name}},\n\n".$en."\n\nYou can follow every step from your orders page.", $titleEn.' · {{order_id}}'],
                    'push' => [$en, $titleEn],
                ],
                'bn' => [
                    'sms' => ['Skydrop Retail: অর্ডার {{order_id}}: '.$bn, 'off' => $smsOff], // no link: keeps Bangla within 2 parts
                    'email' => ["প্রিয় {{name}},\n\n".$bn."\n\nঅর্ডার পেজ থেকে প্রতিটি ধাপ দেখতে পারবেন।", $titleBn.' · {{order_id}}'],
                    'push' => [$bn, $titleBn],
                ],
            ],
        ];
    }
}
