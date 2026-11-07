<?php

namespace App\Services\Tracking\Drivers;

/** TikTok Events API v1.3 (business-api.tiktok.com/open_api/v1.3/event/track/). Dedupes with the TikTok Pixel by event_id. */
class TikTokDriver extends HttpDriver
{
    public const NAMES = [
        'PageView' => 'Pageview', 'ViewContent' => 'ViewContent', 'Search' => 'Search', 'AddToCart' => 'AddToCart',
        'AddToWishlist' => 'AddToWishlist', 'InitiateCheckout' => 'InitiateCheckout', 'AddPaymentInfo' => 'AddPaymentInfo',
        'Purchase' => 'CompletePayment', 'CompleteRegistration' => 'CompleteRegistration', 'Lead' => 'SubmitForm',
    ];

    public function name(): string
    {
        return 'tiktok';
    }

    public function configured(): bool
    {
        return $this->setting('tiktok.pixel_id') !== '' && $this->setting('tiktok.access_token') !== '';
    }

    public function supports(string $event): bool
    {
        return isset(self::NAMES[$event]);
    }

    public function payload(array $e): array
    {
        $u = $e['user'] ?? [];
        $items = $this->items($e);
        $body = [
            'event_source' => 'web',
            'event_source_id' => $this->setting('tiktok.pixel_id'),
            'data' => [array_filter([
                'event' => self::NAMES[$e['event']] ?? $e['event'],
                'event_time' => (int) ($e['event_time'] ?? time()),
                'event_id' => $e['event_id'],
                'user' => array_filter([
                    'email' => $u['em'] ?? null, 'phone' => $u['ph_e164'] ?? null, 'external_id' => $u['external_id'] ?? null,
                    'ttclid' => $u['ttclid'] ?? null, 'ttp' => $u['ttp'] ?? null, 'ip' => $u['ip'] ?? null, 'user_agent' => $u['ua'] ?? null,
                ]) ?: null,
                'page' => array_filter(['url' => $e['url'] ?? null, 'referrer' => $e['referrer'] ?? null]) ?: null,
                'properties' => array_filter([
                    'currency' => $e['currency'] ?? 'BDT',
                    'value' => isset($e['value']) ? (float) $e['value'] : null,
                    'content_type' => $items ? 'product' : null,
                    'contents' => $items ? array_map(fn ($i) => array_filter([
                        'content_id' => $i['id'], 'content_name' => $i['title'] ?? null, 'price' => $i['price'] ?? null, 'quantity' => $i['qty'] ?? 1,
                    ], fn ($v) => $v !== null), $items) : null,
                    'query' => $e['search'] ?? null,
                    'order_id' => $e['order_id'] ?? null,
                ], fn ($v) => $v !== null),
            ])],
        ];
        if ($code = $this->setting('tiktok.test_event_code')) {
            $body['test_event_code'] = $code;
        }

        return $body;
    }

    public function send(array $e): array
    {
        $r = $this->http()->withHeaders(['Access-Token' => $this->setting('tiktok.access_token')])
            ->post('https://business-api.tiktok.com/open_api/v1.3/event/track/', $this->payload($e));
        // TikTok answers HTTP 200 with { code: 0 } on success; any other code is an error.
        $code = $r->json('code');
        $bodyError = $r->successful() && $code !== null && (int) $code !== 0 ? 'TikTok code '.$code.': '.$r->json('message') : null;

        return $this->result($r, $bodyError);
    }
}
