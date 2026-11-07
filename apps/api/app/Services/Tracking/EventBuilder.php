<?php

namespace App\Services\Tracking;

use App\Models\Order;
use App\Models\User;

/** Builds the normalised server-side event (see Drivers\TrackingDriver) from a /track request or an order. */
final class EventBuilder
{
    /** Events the storefront may send to POST /track. Purchase is sent by the API itself (order observer). */
    public const BROWSER_EVENTS = ['PageView', 'ViewContent', 'Search', 'AddToCart', 'AddToWishlist', 'InitiateCheckout',
        'AddShippingInfo', 'AddPaymentInfo', 'CompleteRegistration', 'Lead'];

    /** Deterministic id shared by the browser Pixel/gtag/ttq Purchase and the server Purchase (dedupe). */
    public static function purchaseEventId(string $orderId): string
    {
        return 'purchase_'.strtoupper($orderId);
    }

    public static function deliveredEventId(string $orderId): string
    {
        return 'delivered_'.strtoupper($orderId);
    }

    /** @param array $d validated POST /track body */
    public static function fromTrack(array $d, ?string $ip, ?string $ua, ?User $user): array
    {
        $u = $d['user'] ?? [];
        $hashed = $user
            ? UserData::forUser($user)
            : UserData::fromParts($u['phone'] ?? null, $u['email'] ?? null);

        return [
            'event' => $d['event'],
            'event_id' => $d['eventId'],
            'event_time' => time(),
            'url' => $d['url'] ?? null,
            'referrer' => $d['referrer'] ?? null,
            'currency' => 'BDT',
            'value' => isset($d['value']) ? round((float) $d['value'], 2) : null,
            'order_id' => $d['orderId'] ?? null,
            'search' => isset($d['search']) ? mb_substr((string) $d['search'], 0, 200) : null,
            'items' => self::items($d['items'] ?? []),
            'user' => array_filter([
                'ip' => $ip, 'ua' => $ua ? mb_substr($ua, 0, 500) : null,
                'fbp' => $d['fbp'] ?? null, 'fbc' => $d['fbc'] ?? null, 'ttp' => $d['ttp'] ?? null,
                'ttclid' => $d['ttclid'] ?? null, 'gclid' => $d['gclid'] ?? null, 'ga_client_id' => $d['gaClientId'] ?? null,
            ] + $hashed),
        ];
    }

    /** Purchase (or Delivered) for an order, using the ids captured at checkout (orders.attribution). */
    public static function fromOrder(Order $order, string $event = 'Purchase'): array
    {
        $order->loadMissing(['items', 'user']);
        $a = $order->attribution ?? [];
        $touch = $a['last'] ?? $a['first'] ?? [];

        return [
            'event' => $event,
            'event_id' => $event === 'Purchase' ? self::purchaseEventId($order->id) : self::deliveredEventId($order->id),
            'event_time' => time(),
            'url' => rtrim((string) config('retail.web_url'), '/').'/checkout/success?order='.$order->id,
            'referrer' => null,
            'currency' => 'BDT',
            'value' => round((float) $order->total_bdt, 2),
            'order_id' => $order->id,
            'search' => null,
            'items' => $order->isGoods() ? $order->items->map(fn ($i) => [
                'id' => (string) $i->product_id, 'title' => mb_substr((string) $i->title, 0, 150), 'brand' => null,
                'category' => $i->market, 'price' => round((float) $i->unit_bdt, 2), 'qty' => (int) $i->qty,
            ])->values()->all() : self::digitalItems($order),
            'user' => array_filter([
                'ip' => $a['ip'] ?? null, 'ua' => $a['ua'] ?? null, 'fbp' => $a['fbp'] ?? null, 'fbc' => $a['fbc'] ?? null,
                'ttp' => $a['ttp'] ?? null, 'ttclid' => $touch['ttclid'] ?? null, 'gclid' => $touch['gclid'] ?? null,
                'ga_client_id' => $a['gaClientId'] ?? null,
            ] + UserData::forUser($order->user, $order->address)),
        ];
    }

    /** A Skydrop Plus purchase has no order items: one synthetic line for the plan. */
    private static function digitalItems(Order $order): array
    {
        $plan = ($order->meta['plan_slug'] ?? null) ?: (($order->meta['plan_id'] ?? null) ? 'plan-'.$order->meta['plan_id'] : 'plan');

        return [[
            'id' => mb_substr('plus-'.$plan, 0, 60), 'title' => mb_substr((string) ($order->meta['plan_name'] ?? 'Skydrop Plus'), 0, 150), 'brand' => 'Skydrop Plus',
            'category' => (string) $order->kind, 'price' => round((float) $order->total_bdt, 2), 'qty' => 1,
        ]];
    }

    /** @return array<int, array{id: string, title: ?string, brand: ?string, category: ?string, price: ?float, qty: int}> */
    public static function items(array $raw): array
    {
        $out = [];
        foreach (array_slice($raw, 0, 50) as $i) {
            if (! is_array($i) || empty($i['id'])) {
                continue;
            }
            $out[] = [
                'id' => mb_substr((string) $i['id'], 0, 60),
                'title' => isset($i['title']) ? mb_substr((string) $i['title'], 0, 150) : null,
                'brand' => isset($i['brand']) ? mb_substr((string) $i['brand'], 0, 80) : null,
                'category' => isset($i['category']) ? mb_substr((string) $i['category'], 0, 80) : null,
                'price' => isset($i['price']) && is_numeric($i['price']) ? round((float) $i['price'], 2) : null,
                'qty' => max(1, min(99, (int) ($i['qty'] ?? 1))),
            ];
        }

        return $out;
    }
}
