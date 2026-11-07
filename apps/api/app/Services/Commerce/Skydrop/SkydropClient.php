<?php

namespace App\Services\Commerce\Skydrop;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Skydrop courier API (app.skydrop.online). One two-leg consignment per order:
 *   leg 1: Skydrop Kolkata hub (items bought in India land here) -> Dhaka intake
 *   leg 2: Dhaka intake -> customer's door, collecting COD = unpaid balance.
 * SKYDROP_MODE=mock (default) fakes consignment ids and statuses; `live` calls the API with SKYDROP_API_TOKEN.
 *
 * TODO(credentials): endpoint paths/field names follow the Skydrop seller API (orders via API + webhooks).
 * Confirm them against the API docs for the Skydrop Retail seller account before switching to live.
 */
class SkydropClient
{
    /** Skydrop consignment events -> our order statuses. */
    public const EVENT_MAP = [
        'consignment.created' => null,
        'picked_up' => 'india_hub', 'received_at_origin_hub' => 'india_hub', 'india_hub' => 'india_hub', 'at_kolkata_hub' => 'india_hub',
        'in_transit' => 'in_transit', 'border_crossing' => 'in_transit', 'customs_clearance' => 'in_transit', 'dispatched_to_destination' => 'in_transit',
        'dhaka_intake' => 'dhaka_hub', 'received_at_destination_hub' => 'dhaka_hub', 'dhaka_hub' => 'dhaka_hub',
        'out_for_delivery' => 'out_for_delivery', 'assigned_to_rider' => 'out_for_delivery',
        'delivered' => 'delivered',
    ];

    public function mock(): bool
    {
        return config('retail.skydrop.mode', 'mock') !== 'live';
    }

    private function http(): PendingRequest
    {
        $token = config('retail.skydrop.token');
        if (! $token) {
            throw new RuntimeException('SKYDROP_API_TOKEN is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('retail.skydrop.base_url'), '/'))->withToken($token)->acceptJson()->timeout(20)->retry(2, 500, throw: false);
    }

    /** Consignment payload (also handy for debugging in the admin). */
    public function payload(Order $order): array
    {
        $order->loadMissing('items');
        $a = $order->address;
        $weight = max(0.5, round($order->items->sum(fn ($i) => $i->weight_kg * $i->qty), 3));

        return [
            'reference' => $order->id,
            'tracking_code' => $order->tracking_code,
            'type' => 'two_leg',
            'legs' => [
                ['from' => ['hub' => config('retail.skydrop.hub', 'Kolkata'), 'country' => 'IN', 'pincode' => config('retail.pincode')],
                    'to' => ['hub' => 'Dhaka', 'type' => 'intake', 'country' => 'BD']],
                ['from' => ['hub' => 'Dhaka', 'type' => 'intake', 'country' => 'BD'],
                    'to' => ['name' => $a['name'] ?? '', 'phone' => $a['phone'] ?? '', 'division' => $a['division'] ?? '',
                        'district' => $a['district'] ?? '', 'area' => $a['area'] ?? '', 'address' => $a['line'] ?? '', 'country' => 'BD']],
            ],
            'parcel' => [
                'weight_kg' => $weight,
                'pieces' => (int) $order->items->sum('qty'),
                'declared_value_bdt' => (int) round($order->subtotal_bdt),
                'description' => mb_substr($order->items->map(fn ($i) => $i->qty.'x '.$i->title)->implode('; '), 0, 500),
                'items' => $order->items->map(fn ($i) => ['sku' => $i->product_id, 'market' => $i->market, 'title' => mb_substr($i->title, 0, 120),
                    'qty' => $i->qty, 'weight_kg' => $i->weight_kg, 'value_bdt' => (int) round($i->unit_bdt)])->values()->all(),
            ],
            'cod_amount_bdt' => (int) ceil($order->balanceBdt()),
            'webhook_url' => url('/api/v1/webhooks/skydrop'),
        ];
    }

    /** @return array{consignmentId: string, trackingCode: string, status: string} */
    public function createConsignment(Order $order): array
    {
        if ($this->mock()) {
            return ['consignmentId' => 'MOCK-CN-'.Str::upper(Str::random(8)), 'trackingCode' => (string) $order->tracking_code, 'status' => 'created'];
        }
        $res = $this->http()->post('/v1/consignments', $this->payload($order));
        if (! $res->successful()) {
            throw new RuntimeException('Skydrop createConsignment failed ('.$res->status().'): '.Str::limit($res->body(), 300));
        }

        return [
            'consignmentId' => (string) ($res->json('data.id') ?? $res->json('id')),
            'trackingCode' => (string) ($res->json('data.tracking_code') ?? $res->json('tracking_code') ?? $order->tracking_code),
            'status' => (string) ($res->json('data.status') ?? 'created'),
        ];
    }

    /** @return array{status: ?string, event: ?string, events: array} status is already mapped to our order status */
    public function getStatus(string $trackingCode): array
    {
        if ($this->mock()) {
            $order = Order::query()->where('tracking_code', $trackingCode)->first();

            return ['status' => $order?->status, 'event' => null, 'events' => []];
        }
        $res = $this->http()->get('/v1/consignments/track/'.rawurlencode($trackingCode));
        if (! $res->successful()) {
            throw new RuntimeException('Skydrop getStatus failed ('.$res->status().')');
        }
        $event = (string) ($res->json('data.status') ?? $res->json('status'));

        return ['status' => self::mapEvent($event), 'event' => $event, 'events' => (array) ($res->json('data.events') ?? [])];
    }

    public function cancel(Order $order): bool
    {
        if ($this->mock() || ! $order->skydrop_consignment_id) {
            return true;
        }

        return $this->http()->post('/v1/consignments/'.rawurlencode($order->skydrop_consignment_id).'/cancel', ['reason' => 'Order cancelled'])->successful();
    }

    public static function mapEvent(?string $event): ?string
    {
        $key = strtolower(str_replace([' ', '-', '.'], '_', (string) $event));
        $key = preg_replace('/^consignment_/', '', $key);

        return self::EVENT_MAP[$key] ?? self::EVENT_MAP[(string) $event] ?? null;
    }

    public static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('retail.skydrop.webhook_secret'));
    }

    public static function validSignature(string $body, ?string $header): bool
    {
        $secret = (string) config('retail.skydrop.webhook_secret');
        if ($secret === '' || ! $header) {
            return false;
        }
        $header = preg_replace('/^sha256=/i', '', trim($header));

        return hash_equals(self::sign($body), strtolower($header));
    }
}
