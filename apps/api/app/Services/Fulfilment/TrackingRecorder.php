<?php

namespace App\Services\Fulfilment;

use App\Models\Fulfilment\SkydropEvent;
use App\Models\Order;
use App\Services\Commerce\Skydrop\SkydropClient;
use Illuminate\Support\Carbon;

/**
 * Stores every Skydrop webhook event (skydrop_events), keeps the latest rider location on the order and saves the
 * proof of delivery. Called from SkydropWebhookController; never blocks the status update.
 */
class TrackingRecorder
{
    public function record(Order $order, string $event, array $data): ?SkydropEvent
    {
        [$lat, $lng] = $this->coords($data);
        $at = isset($data['at']) ? rescue(fn () => Carbon::parse($data['at']), now(), false) : now();
        $row = SkydropEvent::query()->create([
            'order_id' => $order->id, 'event' => mb_substr($event ?: 'unknown', 0, 60), 'status' => SkydropClient::mapEvent($event),
            'lat' => $lat, 'lng' => $lng, 'note' => isset($data['note']) ? mb_substr((string) $data['note'], 0, 250) : null,
            'data' => array_intersect_key($data, array_flip(['event', 'status', 'note', 'rider', 'location', 'pod', 'proof_of_delivery', 'lat', 'lng', 'hub'])),
            'at' => $at,
        ]);
        $updates = [];
        if ($lat !== null && $lng !== null) {
            $updates['rider_location'] = ['lat' => $lat, 'lng' => $lng, 'at' => $at->toIso8601String(), 'name' => $data['rider']['name'] ?? null];
        }
        if ($pod = $this->pod($data, $at)) {
            $updates['pod'] = $pod;
        }
        if ($updates) {
            $order->forceFill($updates)->save();
        }

        return $row;
    }

    /** @return array{0: ?float, 1: ?float} */
    private function coords(array $d): array
    {
        foreach ([$d, $d['location'] ?? null, $d['rider'] ?? null, $d['rider']['location'] ?? null] as $src) {
            if (is_array($src) && isset($src['lat'], $src['lng']) && is_numeric($src['lat']) && is_numeric($src['lng'])) {
                $lat = (float) $src['lat'];
                $lng = (float) $src['lng'];
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    return [$lat, $lng];
                }
            }
        }

        return [null, null];
    }

    private function pod(array $d, Carbon $at): ?array
    {
        $src = $d['pod'] ?? $d['proof_of_delivery'] ?? null;
        $src = is_array($src) ? $src : $d;
        $photo = self::url($src['photo_url'] ?? $src['photoUrl'] ?? $src['photo'] ?? null);
        $sig = self::url($src['signature_url'] ?? $src['signatureUrl'] ?? $src['signature'] ?? null);
        if (! $photo && ! $sig) {
            return null;
        }

        return ['photoUrl' => $photo, 'signatureUrl' => $sig, 'receivedBy' => isset($src['received_by']) ? mb_substr((string) $src['received_by'], 0, 80) : null,
            'at' => $at->toIso8601String()];
    }

    private static function url(mixed $v): ?string
    {
        return is_string($v) && preg_match('#^https?://#i', $v) ? mb_substr($v, 0, 1000) : null;
    }

    /** Dev helper (retail:simulate-tracking --rider): rider pings moving from the Dhaka hub to the customer's district. */
    public function simulateRider(Order $order, int $pings = 5): int
    {
        $points = app(OrderTracking::class)->points($order);
        $from = $points[2];
        $to = $points[3];
        for ($i = 1; $i <= $pings; $i++) {
            $f = $i / ($pings + 1);
            $this->record($order, 'rider_location', [
                'lat' => round($from['lat'] + ($to['lat'] - $from['lat']) * $f, 5),
                'lng' => round($from['lng'] + ($to['lng'] - $from['lng']) * $f, 5),
                'rider' => ['name' => 'Demo rider'], 'at' => now()->subSeconds(($pings - $i) * 30)->toIso8601String(),
            ]);
        }

        return $pings;
    }
}
