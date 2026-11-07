<?php

namespace App\Services\Fraud\Couriers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Steadfast (packzy) merchant fraud check: GET https://portal.packzy.com/api/v1/fraud_check/{phone}
 * with the merchant Api-Key / Secret-Key headers (same keys as the Steadfast courier API).
 *
 * TODO(owner): verify the response field names against a live call from the Steadfast merchant account;
 * the parser below accepts the shapes seen in the wild (total_parcels / total_delivered / total_cancelled,
 * or success / cancel / total under "data").
 */
class SteadfastDriver implements CourierFraudDriver
{
    public function id(): string
    {
        return 'steadfast';
    }

    public function enabled(): bool
    {
        $c = CourierCreds::get('steadfast');

        return $c['enabled'] && filled($c['api_key'] ?? null) && filled($c['secret_key'] ?? null);
    }

    public function check(string $phone): array
    {
        $c = CourierCreds::get('steadfast');
        $phone = CourierCreds::localPhone($phone);
        $r = Http::withHeaders(['Api-Key' => $c['api_key'], 'Secret-Key' => $c['secret_key'], 'Accept' => 'application/json'])
            ->timeout(8)->get(str_replace('{phone}', rawurlencode($phone), $c['url']));
        if (! $r->successful()) {
            throw new RuntimeException('Steadfast HTTP '.$r->status());
        }
        $j = (array) $r->json();
        $d = (array) ($j['data'] ?? $j);
        $delivered = (int) ($d['total_delivered'] ?? $d['delivered'] ?? $d['success'] ?? $d['success_parcel'] ?? 0);
        $cancelled = (int) ($d['total_cancelled'] ?? $d['cancelled'] ?? $d['cancel'] ?? $d['cancelled_parcel'] ?? 0);
        $total = (int) ($d['total_parcels'] ?? $d['total_parcel'] ?? $d['total'] ?? ($delivered + $cancelled));

        return ['total' => max($total, $delivered + $cancelled), 'delivered' => $delivered, 'cancelled' => $cancelled, 'raw' => $j];
    }
}
