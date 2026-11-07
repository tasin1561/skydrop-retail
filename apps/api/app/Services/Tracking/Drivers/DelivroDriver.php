<?php

namespace App\Services\Tracking\Drivers;

/**
 * Delivro (https://delivro.app): the owner's server-side tracking SaaS. It receives one generic event and
 * fans it out to Meta CAPI / GA4 / TikTok on its side.
 *
 * TODO(delivro): confirm the payload shape, endpoint path, auth header and success response against Delivro's
 * ingest API. Only payload() needs to change; it is the single place that defines the wire format.
 */
class DelivroDriver extends HttpDriver
{
    public function name(): string
    {
        return 'delivro';
    }

    public function configured(): bool
    {
        return $this->setting('delivro.endpoint') !== '' && $this->setting('delivro.key') !== '';
    }

    public function supports(string $event): bool
    {
        return true;
    }

    /** Generic JSON event (hashed user data only). */
    public function payload(array $e): array
    {
        $u = $e['user'] ?? [];

        return [
            'source' => 'skydrop-retail',
            'event_name' => $e['event'],
            'event_id' => $e['event_id'],
            'event_time' => (int) ($e['event_time'] ?? time()),
            'action_source' => 'website',
            'event_source_url' => $e['url'] ?? null,
            'referrer' => $e['referrer'] ?? null,
            'currency' => $e['currency'] ?? 'BDT',
            'value' => isset($e['value']) ? (float) $e['value'] : null,
            'order_id' => $e['order_id'] ?? null,
            'search_string' => $e['search'] ?? null,
            'content_type' => 'product',
            'content_ids' => array_column($this->items($e), 'id'),
            'contents' => $this->items($e),
            'user_data' => [
                'client_ip_address' => $u['ip'] ?? null,
                'client_user_agent' => $u['ua'] ?? null,
                'fbp' => $u['fbp'] ?? null, 'fbc' => $u['fbc'] ?? null,
                'ttp' => $u['ttp'] ?? null, 'ttclid' => $u['ttclid'] ?? null,
                'gclid' => $u['gclid'] ?? null, 'ga_client_id' => $u['ga_client_id'] ?? null,
                'ph' => $u['ph'] ?? null, 'em' => $u['em'] ?? null, 'external_id' => $u['external_id'] ?? null,
                'fn' => $u['fn'] ?? null, 'ln' => $u['ln'] ?? null, 'ct' => $u['ct'] ?? null, 'country' => $u['country'] ?? null,
            ],
        ];
    }

    public function send(array $e): array
    {
        $r = $this->http()->withToken($this->setting('delivro.key'))->post($this->setting('delivro.endpoint'), $this->payload($e));

        return $this->result($r);
    }
}
