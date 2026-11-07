<?php

namespace App\Services\Fraud\Couriers;

use RuntimeException;

/**
 * CarryBee customer history.
 *
 * TODO(owner): no public endpoint known. Expected shape once confirmed:
 * {"data": {"total": int, "delivered": int, "returned": int}}. Enable with CARRYBEE_FRAUD_ENABLED=true.
 */
class CarryBeeDriver implements CourierFraudDriver
{
    public function id(): string
    {
        return 'carrybee';
    }

    public function enabled(): bool
    {
        $c = config('finance.fraud.couriers.carrybee');

        return ($c['enabled'] ?? false) && filled($c['token'] ?? null) && filled($c['base_url'] ?? null);
    }

    public function check(string $phone): array
    {
        throw new RuntimeException('CarryBee fraud check is not implemented yet (see TODO in CarryBeeDriver).');
    }
}
