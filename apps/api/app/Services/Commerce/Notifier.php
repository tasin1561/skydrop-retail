<?php

namespace App\Services\Commerce;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Messaging\Events;
use App\Services\Messaging\Messenger;
use App\Services\Messaging\Prefs;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Customer notifications. Each method builds the placeholder data for an event and hands it to the Messenger,
 * which renders the message_templates (en/bn) and queues SMS / email / push deliveries plus the in-app item.
 * Public method signatures are relied on by OrderService, PaymentService, OrderWorkflow, CheckProductWatches and the admin.
 */
class Notifier
{
    public const MARKETS = ['amazon' => 'Amazon · amazon.in', 'flipkart' => 'Flipkart · flipkart.com', 'bigbasket' => 'BigBasket · bigbasket.com'];

    private const L = [
        'en' => ['subtotal' => 'Items', 'shipping' => 'Shipping (India → BD)', 'coupon' => 'Coupon', 'points' => 'Points used', 'total' => 'Total',
            'paid' => 'Paid', 'due' => 'Due on delivery', 'qty' => 'Qty', 'free' => 'Free', 'invoice' => 'Download invoice', 'track' => 'Track your order',
            'pay' => 'Pay now', 'retry' => 'Try payment again', 'review' => 'Write a review', 'shop' => 'Start shopping', 'view_item' => 'View item',
            'cart' => 'Return to cart', 'verify' => 'Confirm email', 'points_link' => 'See my points', 'earn' => 'You will earn :n points on delivery.',
            'order' => 'Order', 'payment' => 'Payment', 'rider' => 'Pay the Skydrop rider :due in cash when your parcel arrives.'],
        'bn' => ['subtotal' => 'পণ্য', 'shipping' => 'শিপিং (ভারত → বাংলাদেশ)', 'coupon' => 'কুপন', 'points' => 'পয়েন্ট ব্যবহার', 'total' => 'মোট',
            'paid' => 'পরিশোধিত', 'due' => 'ডেলিভারিতে দেবেন', 'qty' => 'পরিমাণ', 'free' => 'ফ্রি', 'invoice' => 'ইনভয়েস ডাউনলোড', 'track' => 'অর্ডার ট্র্যাক করুন',
            'pay' => 'এখনই পে করুন', 'retry' => 'আবার পেমেন্ট করুন', 'review' => 'রিভিউ লিখুন', 'shop' => 'কেনাকাটা শুরু করুন', 'view_item' => 'পণ্যটি দেখুন',
            'cart' => 'কার্টে ফিরে যান', 'verify' => 'ইমেইল নিশ্চিত করুন', 'points_link' => 'আমার পয়েন্ট', 'earn' => 'ডেলিভারির পর :n পয়েন্ট পাবেন।',
            'order' => 'অর্ডার', 'payment' => 'পেমেন্ট', 'rider' => 'পার্সেল পৌঁছালে Skydrop রাইডারকে নগদে :due দিন।'],
    ];

    public function __construct(private Messenger $messenger) {}

    // ---- existing public API ---------------------------------------------------------------------

    public function orderPlaced(Order $order): void
    {
        $this->forOrder($order, 'order_placed', fn (string $loc) => [
            'total' => $this->bdt($order->total_bdt, $loc), 'points_to_earn' => (int) $order->points_to_earn,
            'items_count' => (int) $order->items->sum('qty'),
        ], fn (string $loc) => [
            'blocks' => [$this->itemsBlock($order, $loc), $this->totalsBlock($order, $loc)],
            'cta' => ['label' => $this->t($loc, 'track'), 'url' => $this->orderUrl($order)],
            'extraLinks' => [['label' => $this->t($loc, 'invoice'), 'url' => $this->invoiceUrl($order)]],
        ]);
    }

    public function paymentReceived(Order $order, float $amount): void
    {
        if (! $order->isGoods()) {
            $this->digitalPaymentReceived($order, $amount);

            return;
        }
        $due = $order->balanceBdt();
        $this->forOrder($order, 'payment_received', fn (string $loc) => [
            'amount' => $this->bdt($amount, $loc), 'due' => $due > 0 ? $this->bdt($due, $loc) : null, 'total' => $this->bdt($order->total_bdt, $loc),
        ], fn (string $loc) => [
            'blocks' => array_values(array_filter([
                $this->totalsBlock($order, $loc),
                $due > 0 ? ['type' => 'note', 'tone' => 'info', 'text' => str_replace(':due', $this->bdt($due, $loc), $this->t($loc, 'rider'))] : null,
            ])),
            'cta' => ['label' => $this->t($loc, 'track'), 'url' => $this->orderUrl($order)],
            'extraLinks' => [['label' => $this->t($loc, 'invoice'), 'url' => $this->invoiceUrl($order)]],
        ]);
    }

    /**
     * Gift card / Skydrop Plus purchase paid: no parcel, no rider, nothing to track. The card itself (code) and the
     * membership are delivered by GiftCardService / MembershipService.
     */
    private function digitalPaymentReceived(Order $order, float $amount): void
    {
        $order->loadMissing('user');
        if (! $order->user) {
            return;
        }
        $giftCard = $order->kind === 'gift_card';
        $path = $giftCard ? '/gift-cards' : '/plus';
        $plan = (string) ($order->meta['plan_name'] ?? '');
        $this->messenger->event($order->user, 'digital_payment_received', [
            'order_id' => $order->id, 'amount' => $this->bdt($amount), 'total' => $this->bdt($order->total_bdt),
            'gift_card' => $giftCard, 'plus' => ! $giftCard, 'plan' => $plan !== '' ? $plan : null, 'link' => Messenger::webUrl($path),
        ], ['phone' => $order->address['phone'] ?? null, 'order_id' => $order->id, 'link' => $path, 'data' => ['orderId' => $order->id, 'kind' => $order->kind]]);
    }

    public function statusChanged(Order $order, string $status): void
    {
        $event = Events::forStatus($status);
        if (! $event) {
            return;
        }
        $due = $order->balanceBdt();
        $refunded = in_array($order->payment_status, ['refunded', 'refund_pending'], true);
        $refund = $refunded ? (float) $order->payments()->whereIn('status', ['refunded', 'refund_pending'])->sum('amount_bdt') : 0.0;

        $this->forOrder($order, $event, fn (string $loc) => [
            'due' => $due > 0 && $status !== 'cancelled' ? $this->bdt($due, $loc) : null,
            'total' => $this->bdt($order->total_bdt, $loc),
            'points_earned' => (int) $order->points_earned ?: null,
            'refund' => $refund > 0 ? $this->bdt($refund, $loc) : null,
            'refunded' => $order->payment_status === 'refunded',
            'review_link' => $this->orderUrl($order).'#review',
        ], function (string $loc) use ($order, $status, $due) {
            $o = ['cta' => ['label' => $this->t($loc, $status === 'delivered' ? 'review' : 'track'), 'url' => $this->orderUrl($order).($status === 'delivered' ? '#review' : '')]];
            if ($status === 'out_for_delivery' && $due > 0) {
                $o['blocks'] = [['type' => 'note', 'tone' => 'warn', 'text' => str_replace(':due', $this->bdt($due, $loc), $this->t($loc, 'rider'))]];
            }
            if (in_array($status, ['delivered', 'cancelled'], true)) {
                $o['blocks'] = [$this->itemsBlock($order, $loc)];
            }

            return $o;
        });
    }

    public function priceDrop(User $user, string $title, int $priceBdt, string $productId): void
    {
        $loc = Prefs::locale($user);
        $link = '/p/'.rawurlencode($productId);
        $this->messenger->event($user, 'price_drop', [
            'title' => mb_strimwidth($title, 0, 60, '…'), 'price' => $this->bdt($priceBdt, $loc), 'link' => Messenger::webUrl($link),
        ], ['link' => $link, 'cta' => ['label' => $this->t($loc, 'view_item'), 'url' => Messenger::webUrl($link)], 'data' => ['productId' => $productId],
            'image' => $this->productImage($productId)]);
    }

    public function backInStock(User $user, string $title, string $productId): void
    {
        $loc = Prefs::locale($user);
        $link = '/p/'.rawurlencode($productId);
        $this->messenger->event($user, 'back_in_stock', [
            'title' => mb_strimwidth($title, 0, 60, '…'), 'link' => Messenger::webUrl($link),
        ], ['link' => $link, 'cta' => ['label' => $this->t($loc, 'view_item'), 'url' => Messenger::webUrl($link)], 'data' => ['productId' => $productId],
            'image' => $this->productImage($productId)]);
    }

    /** Free-form message from staff (admin panel). $channel: sms | email | push | inapp | both (sms + email). Returns false if nothing could be sent. */
    public function custom(User $user, string $text, string $channel = 'sms'): bool
    {
        $channels = $channel === 'both' ? ['sms', 'email'] : [$channel];
        $logs = $this->messenger->raw($user, array_merge($channels, ['inapp']), 'Message from Skydrop Retail', $text);

        return $logs !== [] || $channel === 'inapp';
    }

    // ---- new events ------------------------------------------------------------------------------

    public function welcome(User $user): void
    {
        $this->messenger->event($user, 'welcome', ['points' => (int) $user->points_balance ?: null, 'link' => Messenger::webUrl('/')],
            ['link' => '/', 'cta' => ['label' => $this->t(Prefs::locale($user), 'shop'), 'url' => Messenger::webUrl('/')]]);
    }

    public function paymentFailed(Order $order, ?Payment $payment = null): void
    {
        $amount = $payment ? (float) $payment->amount_bdt : $order->balanceBdt();
        $this->forOrder($order, 'payment_failed', fn (string $loc) => [
            'amount' => $this->bdt($amount, $loc), 'pay_link' => $this->payUrl($order),
        ], fn (string $loc) => ['cta' => ['label' => $this->t($loc, 'retry'), 'url' => $this->payUrl($order)], 'link' => '/orders/'.$order->id.'?pay=1']);
    }

    public function paymentPending(Order $order): void
    {
        $amount = app(Payments\PaymentService::class)->amountNow($order);
        $this->forOrder($order, 'payment_pending', fn (string $loc) => [
            'amount' => $this->bdt($amount, $loc), 'pay_link' => $this->payUrl($order),
        ], fn (string $loc) => ['cta' => ['label' => $this->t($loc, 'pay'), 'url' => $this->payUrl($order)], 'link' => '/orders/'.$order->id.'?pay=1']);
    }

    public function reviewRequest(Order $order): void
    {
        $order->loadMissing('items');
        $first = $order->items->first();
        $this->forOrder($order, 'review_request', fn () => [
            'product' => $first ? mb_strimwidth($first->title, 0, 50, '…') : 'your items', 'review_link' => $this->orderUrl($order).'#review',
        ], fn (string $loc) => [
            'cta' => ['label' => $this->t($loc, 'review'), 'url' => $this->orderUrl($order).'#review'], 'blocks' => [$this->itemsBlock($order, $loc)],
            'image' => $first?->image, 'channels' => ['email', 'push'],
        ]);
    }

    public function pointsExpiring(User $user, int $points, Carbon $expiresOn): void
    {
        $loc = Prefs::locale($user);
        $rules = app(PointsService::class)->rules();
        $value = (int) floor($points / max(1, $rules['redeem_block']) * $rules['redeem_block_bdt']);
        $this->messenger->event($user, 'points_expiring', [
            'points' => $points, 'value' => $this->bdt($value, $loc), 'expires_on' => $expiresOn->format('j M Y'),
            'link' => Messenger::webUrl('/account/points'),
        ], ['link' => '/account/points', 'cta' => ['label' => $this->t($loc, 'points_link'), 'url' => Messenger::webUrl('/account/points')]]);
    }

    /** $stage: 1h (push) | 24h (SMS + email). */
    public function cartReminder(Cart $cart, string $stage): void
    {
        $user = $cart->user;
        if (! $user) {
            return;
        }
        $loc = Prefs::locale($user);
        $items = collect($cart->items);
        $first = $items->first();
        $count = (int) $items->sum('qty');
        $groups = $items->groupBy(fn ($i) => $i['market'] ?? 'amazon')->map(fn ($g, $m) => [
            'store' => self::MARKETS[$m] ?? $m,
            'items' => $g->map(fn ($i) => ['title' => (string) ($i['title'] ?? $i['productId']), 'variant' => $i['variant'] ?? null, 'image' => $i['image'] ?? null,
                'qtyLabel' => $this->t($loc, 'qty').' '.(int) ($i['qty'] ?? 1), 'line' => $this->bdt((float) ($i['unitBdt'] ?? 0) * (int) ($i['qty'] ?? 1), $loc)])->values()->all(),
        ])->values()->all();

        $this->messenger->event($user, $stage === '1h' ? 'cart_reminder_1h' : 'cart_reminder_24h', [
            'count' => $count, 'total' => $this->bdt($cart->total_bdt, $loc), 'title' => mb_strimwidth((string) ($first['title'] ?? ''), 0, 40, '…'),
            'more' => max(0, $items->count() - 1) ?: null, 'link' => Messenger::webUrl('/cart'),
        ], [
            'link' => '/cart', 'image' => $first['image'] ?? null, 'channels' => $stage === '1h' ? ['push'] : ['sms', 'email'],
            'inapp' => $stage === '1h', 'blocks' => [['type' => 'items', 'groups' => $groups]],
            'cta' => ['label' => $this->t($loc, 'cart'), 'url' => Messenger::webUrl('/cart')],
        ]);
    }

    public function emailVerification(User $user): void
    {
        if (! $user->email) {
            return;
        }
        $url = URL::temporarySignedRoute('messaging.email.verify', now()->addDays(3), ['user' => $user->id, 'hash' => sha1((string) $user->email)]);
        $this->messenger->event($user, 'email_verify', ['link' => $url], [
            'channels' => ['email'], 'inapp' => false, 'cta' => ['label' => $this->t(Prefs::locale($user), 'verify'), 'url' => $url],
        ]);
    }

    /** Ops email to ALERT_EMAIL (budget alerts, low SMS balance…). */
    public function admin(string $subject, string $text): void
    {
        $this->messenger->admin($subject, $text);
    }

    // ---- helpers ---------------------------------------------------------------------------------

    /**
     * @param  callable(string): array  $data  placeholder data for a locale
     * @param  callable(string): array|null  $opts  messenger options for a locale
     */
    private function forOrder(Order $order, string $event, callable $data, ?callable $opts = null): void
    {
        $order->loadMissing(['user', 'items']);
        $user = $order->user;
        if (! $user) {
            return;
        }
        $loc = Prefs::locale($user);
        $o = $opts ? $opts($loc) : [];
        if (! empty($o['extraLinks'])) {
            $o['blocks'][] = ['type' => 'links', 'links' => $o['extraLinks']];
        }
        unset($o['extraLinks']);
        $this->messenger->event($user, $event, $data($loc) + ['order_id' => $order->id, 'link' => $this->orderUrl($order)], $o + [
            'phone' => $order->address['phone'] ?? null, 'order_id' => $order->id, 'link' => '/orders/'.$order->id,
            'data' => ['orderId' => $order->id],
        ]);
    }

    private function itemsBlock(Order $order, string $loc): array
    {
        $groups = $order->items->groupBy('market')->map(fn ($items, $market) => [
            'store' => self::MARKETS[$market] ?? $market,
            'items' => $items->map(fn ($i) => [
                'title' => $i->title, 'variant' => $i->variant, 'image' => $i->image,
                'qtyLabel' => $this->t($loc, 'qty').' '.$i->qty.' × '.$this->bdt($i->unit_bdt, $loc),
                'line' => $this->bdt($i->unit_bdt * $i->qty, $loc),
            ])->values()->all(),
        ])->values()->all();

        return ['type' => 'items', 'groups' => $groups];
    }

    private function totalsBlock(Order $order, string $loc): array
    {
        $rows = [[$this->t($loc, 'subtotal'), $this->bdt($order->subtotal_bdt, $loc)]];
        $rows[] = [$this->t($loc, 'shipping'), $order->shipping_bdt > 0 ? $this->bdt($order->shipping_bdt, $loc) : $this->t($loc, 'free')];
        if ($order->coupon_discount_bdt > 0) {
            $rows[] = [$this->t($loc, 'coupon').($order->coupon_code ? ' ('.$order->coupon_code.')' : ''), '−'.$this->bdt($order->coupon_discount_bdt, $loc)];
        }
        if ($order->points_discount_bdt > 0) {
            $rows[] = [$this->t($loc, 'points').' ('.$order->points_redeemed.')', '−'.$this->bdt($order->points_discount_bdt, $loc)];
        }
        $rows[] = [$this->t($loc, 'total'), $this->bdt($order->total_bdt, $loc), true];
        if ($order->paid_bdt > 0) {
            $rows[] = [$this->t($loc, 'paid'), $this->bdt($order->paid_bdt, $loc)];
        }
        if ($order->balanceBdt() > 0 && $order->payment_method === 'advance50') {
            $rows[] = [$this->t($loc, 'due'), $this->bdt($order->balanceBdt(), $loc)];
        }

        return ['type' => 'totals', 'rows' => $rows];
    }

    private function productImage(string $productId): ?string
    {
        return rescue(fn () => Product::query()->whereKey($productId)->value('image'), null, false);
    }

    private function orderUrl(Order $order): string
    {
        return Messenger::webUrl('/orders/'.$order->id);
    }

    private function payUrl(Order $order): string
    {
        return Messenger::webUrl('/orders/'.$order->id.'?pay=1');
    }

    /** Signed invoice link (same route as Order.invoiceUrl, longer lifetime for emails). */
    private function invoiceUrl(Order $order): string
    {
        return URL::temporarySignedRoute('orders.invoice.signed', now()->addDays((int) config('messaging.mail.invoice_link_days', 30)), ['id' => $order->id]);
    }

    private function t(string $loc, string $key): string
    {
        return self::L[$loc][$key] ?? self::L['en'][$key] ?? $key;
    }

    private function bdt(float|int $n, string $loc = 'en'): string
    {
        return ($loc === 'bn' ? '৳' : 'BDT ').number_format(round($n));
    }
}
