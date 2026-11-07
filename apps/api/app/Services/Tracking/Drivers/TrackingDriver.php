<?php

namespace App\Services\Tracking\Drivers;

/**
 * A server-side destination. `send` receives the normalised event built by App\Services\Tracking\EventBuilder:
 * { event, event_id, event_time, url, referrer, currency, value, order_id, search, items[{id,title,brand,category,price,qty}],
 *   user{ ip, ua, fbp, fbc, ttp, ttclid, gclid, ga_client_id, ph, ph_e164, em, external_id, fn, ln, ct, country } }
 */
interface TrackingDriver
{
    public function name(): string;

    /** Credentials present (else the destination is skipped). */
    public function configured(): bool;

    /** Whether this destination takes the event at all (else logged as skipped). */
    public function supports(string $event): bool;

    /**
     * Sends one event. Throws on a retryable failure (network, 5xx, 429).
     *
     * @return array{code: int, error: ?string} error set for a permanent failure (4xx)
     */
    public function send(array $event): array;
}
