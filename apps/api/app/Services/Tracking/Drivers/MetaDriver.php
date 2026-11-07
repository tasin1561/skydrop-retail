<?php

namespace App\Services\Tracking\Drivers;

/** Meta Conversions API: POST graph.facebook.com/{version}/{pixel}/events. Dedupes with the browser Pixel by event_id. */
class MetaDriver extends HttpDriver
{
    public const STANDARD = ['PageView', 'ViewContent', 'Search', 'AddToCart', 'AddToWishlist', 'InitiateCheckout', 'AddPaymentInfo',
        'Purchase', 'CompleteRegistration', 'Lead'];

    public function name(): string
    {
        return 'meta';
    }

    public function configured(): bool
    {
        return $this->setting('meta.pixel_id') !== '' && $this->setting('meta.access_token') !== '';
    }

    public function supports(string $event): bool
    {
        return true; // non-standard names (AddShippingInfo, Delivered) go in as custom events
    }

    public function payload(array $e): array
    {
        $u = $e['user'] ?? [];
        $items = $this->items($e);
        $custom = array_filter([
            'currency' => $e['currency'] ?? 'BDT',
            'value' => isset($e['value']) ? (float) $e['value'] : null,
            'content_ids' => $items ? array_column($items, 'id') : null,
            'content_type' => $items ? 'product' : null,
            'contents' => $items ? array_map(fn ($i) => array_filter(['id' => $i['id'], 'quantity' => $i['qty'] ?? 1, 'item_price' => $i['price'] ?? null], fn ($v) => $v !== null), $items) : null,
            'num_items' => $items ? array_sum(array_map(fn ($i) => (int) ($i['qty'] ?? 1), $items)) : null,
            'search_string' => $e['search'] ?? null,
            'order_id' => $e['order_id'] ?? null,
        ], fn ($v) => $v !== null);
        $user = array_filter([
            'client_ip_address' => $u['ip'] ?? null,
            'client_user_agent' => $u['ua'] ?? null,
            'fbp' => $u['fbp'] ?? null,
            'fbc' => $u['fbc'] ?? null,
            'ph' => isset($u['ph']) ? [$u['ph']] : null,
            'em' => isset($u['em']) ? [$u['em']] : null,
            'external_id' => isset($u['external_id']) ? [$u['external_id']] : null,
            'fn' => isset($u['fn']) ? [$u['fn']] : null,
            'ln' => isset($u['ln']) ? [$u['ln']] : null,
            'ct' => isset($u['ct']) ? [$u['ct']] : null,
            'country' => isset($u['country']) ? [$u['country']] : null,
        ]);
        $body = ['data' => [array_filter([
            'event_name' => $e['event'],
            'event_time' => (int) ($e['event_time'] ?? time()),
            'event_id' => $e['event_id'],
            'event_source_url' => $e['url'] ?? null,
            'action_source' => 'website',
            'user_data' => $user ?: (object) [],
            'custom_data' => $custom ?: null,
        ], fn ($v) => $v !== null)]];
        if ($code = $this->setting('meta.test_event_code')) {
            $body['test_event_code'] = $code;
        }

        return $body;
    }

    public function send(array $e): array
    {
        $version = config('retail.tracking.meta.api_version', 'v21.0');
        $url = "https://graph.facebook.com/{$version}/".rawurlencode($this->setting('meta.pixel_id')).'/events';
        $r = $this->http()->post($url.'?access_token='.urlencode($this->setting('meta.access_token')), $this->payload($e));

        return $this->result($r);
    }
}
