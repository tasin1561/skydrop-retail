<?php

namespace App\Services\Fraud\Couriers;

use RuntimeException;

/**
 * Pathao merchant "customer success rate" lookup.
 *
 * TODO(owner): Pathao has no public fraud-check endpoint in the Aladdin/Hermes API docs. The merchant panel uses
 * POST {base_url}/api/v1/user/success with {"phone": "01XXXXXXXXX"} after an OAuth password grant
 * (POST {base_url}/aladdin/api/v1/issue-token with client_id/client_secret/username/password).
 * Expected shape: {"data": {"customer": {"total_delivery": int, "successful_delivery": int}}}.
 * Confirm with Pathao merchant support before enabling (PATHAO_FRAUD_ENABLED=true).
 */
class PathaoDriver implements CourierFraudDriver
{
    public function id(): string
    {
        return 'pathao';
    }

    public function enabled(): bool
    {
        $c = config('finance.fraud.couriers.pathao');

        return ($c['enabled'] ?? false) && filled($c['client_id'] ?? null);
    }

    public function check(string $phone): array
    {
        throw new RuntimeException('Pathao fraud check is not implemented yet (see TODO in PathaoDriver).');
    }

    /** Parser for the expected response, ready for when the endpoint is confirmed. */
    public static function parse(array $j): array
    {
        $c = (array) ($j['data']['customer'] ?? []);
        $total = (int) ($c['total_delivery'] ?? 0);
        $ok = (int) ($c['successful_delivery'] ?? 0);

        return ['total' => $total, 'delivered' => $ok, 'cancelled' => max(0, $total - $ok), 'raw' => $j];
    }
}
