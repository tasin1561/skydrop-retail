<?php

namespace App\Services\Fraud\Couriers;

use RuntimeException;

/**
 * RedX customer delivery history.
 *
 * TODO(owner): RedX's open API has no documented fraud endpoint. The merchant dashboard calls
 * GET https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate?phoneNumber=8801XXXXXXXXX
 * (session cookie). Expected shape: {"data": {"deliveredParcels": "int", "totalParcels": "int"}}.
 * Ask RedX for an API-token equivalent before enabling (REDX_FRAUD_ENABLED=true).
 */
class RedxDriver implements CourierFraudDriver
{
    public function id(): string
    {
        return 'redx';
    }

    public function enabled(): bool
    {
        $c = config('finance.fraud.couriers.redx');

        return ($c['enabled'] ?? false) && filled($c['token'] ?? null);
    }

    public function check(string $phone): array
    {
        throw new RuntimeException('RedX fraud check is not implemented yet (see TODO in RedxDriver).');
    }

    public static function parse(array $j): array
    {
        $total = (int) ($j['data']['totalParcels'] ?? 0);
        $ok = (int) ($j['data']['deliveredParcels'] ?? 0);

        return ['total' => $total, 'delivered' => $ok, 'cancelled' => max(0, $total - $ok), 'raw' => $j];
    }
}
