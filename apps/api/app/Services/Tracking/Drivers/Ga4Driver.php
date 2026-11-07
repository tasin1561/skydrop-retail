<?php

namespace App\Services\Tracking\Drivers;

/**
 * GA4 Measurement Protocol (google-analytics.com/mp/collect). GA4 does not dedupe by event_id, so the browser
 * gtag and this driver should not both send the same event: the storefront only POSTs /track for events it wants
 * server-side, and Purchase uses transaction_id (GA4 dedupes purchases with the same transaction_id).
 */
class Ga4Driver extends HttpDriver
{
    public const NAMES = [
        'PageView' => 'page_view', 'ViewContent' => 'view_item', 'Search' => 'search', 'AddToCart' => 'add_to_cart',
        'AddToWishlist' => 'add_to_wishlist', 'InitiateCheckout' => 'begin_checkout', 'AddShippingInfo' => 'add_shipping_info',
        'AddPaymentInfo' => 'add_payment_info', 'Purchase' => 'purchase', 'CompleteRegistration' => 'sign_up', 'Lead' => 'generate_lead',
        'Delivered' => 'order_delivered',
    ];

    public function name(): string
    {
        return 'ga4';
    }

    public function configured(): bool
    {
        return $this->setting('ga4.measurement_id') !== '' && $this->setting('ga4.api_secret') !== '';
    }

    public function supports(string $event): bool
    {
        return isset(self::NAMES[$event]);
    }

    public function payload(array $e): array
    {
        $u = $e['user'] ?? [];
        $items = $this->items($e);
        $params = array_filter([
            'currency' => $items || isset($e['value']) ? ($e['currency'] ?? 'BDT') : null,
            'value' => isset($e['value']) ? (float) $e['value'] : null,
            'transaction_id' => $e['order_id'] ?? null,
            'search_term' => $e['search'] ?? null,
            'page_location' => $e['url'] ?? null,
            'page_referrer' => $e['referrer'] ?? null,
            'method' => $e['event'] === 'CompleteRegistration' ? 'otp' : null,
            'event_id' => $e['event_id'],
            'engagement_time_msec' => 1,
            'items' => $items ? array_map(fn ($i) => array_filter([
                'item_id' => $i['id'], 'item_name' => $i['title'] ?? null, 'item_brand' => $i['brand'] ?? null,
                'item_category' => $i['category'] ?? null, 'price' => $i['price'] ?? null, 'quantity' => $i['qty'] ?? 1,
            ], fn ($v) => $v !== null), $items) : null,
        ], fn ($v) => $v !== null);

        return array_filter([
            'client_id' => $u['ga_client_id'] ?? $this->fallbackClientId($e),
            'user_id' => $u['external_id'] ?? null,
            'timestamp_micros' => (int) ($e['event_time'] ?? time()) * 1_000_000,
            'events' => [['name' => self::NAMES[$e['event']] ?? strtolower($e['event']), 'params' => $params]],
        ], fn ($v) => $v !== null);
    }

    /** GA4 needs a client_id; without the _ga cookie use a stable pseudo id from the user or event. */
    private function fallbackClientId(array $e): string
    {
        $seed = $e['user']['external_id'] ?? $e['user']['ph'] ?? $e['event_id'];
        $h = hexdec(substr(hash('sha256', (string) $seed), 0, 8));

        return $h.'.'.((int) ($e['event_time'] ?? time()));
    }

    public function send(array $e): array
    {
        $q = http_build_query(['measurement_id' => $this->setting('ga4.measurement_id'), 'api_secret' => $this->setting('ga4.api_secret')]);
        $r = $this->http()->post('https://www.google-analytics.com/mp/collect?'.$q, $this->payload($e));

        return $this->result($r);
    }
}
