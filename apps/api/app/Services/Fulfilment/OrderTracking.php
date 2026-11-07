<?php

namespace App\Services\Fulfilment;

use App\Models\Fulfilment\SkydropEvent;
use App\Models\Order;
use App\Support\Features;
use Illuminate\Support\Carbon;

/**
 * Route view for GET /orders/{id}/tracking: Kolkata hub → Petrapole/Benapole border → Dhaka hub → customer district,
 * the current leg and position, ETA, merged events, the rider location when fresh and proof of delivery.
 */
class OrderTracking
{
    public const HUBS = [
        'kolkata' => ['label' => 'Skydrop Kolkata hub', 'short' => 'Kolkata', 'lat' => 22.5726, 'lng' => 88.3639],
        'border' => ['label' => 'Petrapole / Benapole border', 'short' => 'Benapole', 'lat' => 23.0417, 'lng' => 88.8838],
        'dhaka' => ['label' => 'Skydrop Dhaka hub', 'short' => 'Dhaka hub', 'lat' => 23.8103, 'lng' => 90.4125],
    ];

    /** Approximate district centres (stylised map, not navigation). */
    public const DISTRICTS = [
        'Dhaka' => [23.81, 90.41], 'Gazipur' => [24.00, 90.42], 'Narayanganj' => [23.62, 90.50], 'Narsingdi' => [23.92, 90.72], 'Manikganj' => [23.86, 90.00],
        'Munshiganj' => [23.54, 90.53], 'Tangail' => [24.25, 89.92], 'Faridpur' => [23.61, 89.84], 'Gopalganj' => [23.01, 89.83], 'Kishoreganj' => [24.44, 90.78],
        'Madaripur' => [23.17, 90.20], 'Rajbari' => [23.76, 89.64], 'Shariatpur' => [23.24, 90.43],
        'Chattogram' => [22.36, 91.78], "Cox's Bazar" => [21.43, 92.01], 'Cumilla' => [23.46, 91.18], 'Feni' => [23.02, 91.40], 'Noakhali' => [22.87, 91.10],
        'Lakshmipur' => [22.94, 90.83], 'Chandpur' => [23.23, 90.67], 'Brahmanbaria' => [23.96, 91.11], 'Rangamati' => [22.65, 92.18], 'Khagrachhari' => [23.12, 91.98],
        'Bandarban' => [22.20, 92.22],
        'Rajshahi' => [24.37, 88.60], 'Bogura' => [24.85, 89.37], 'Pabna' => [24.01, 89.24], 'Sirajganj' => [24.45, 89.70], 'Natore' => [24.41, 89.00],
        'Naogaon' => [24.81, 88.94], 'Chapainawabganj' => [24.60, 88.27], 'Joypurhat' => [25.10, 89.02],
        'Khulna' => [22.85, 89.54], 'Jashore' => [23.17, 89.21], 'Satkhira' => [22.72, 89.07], 'Bagerhat' => [22.65, 89.79], 'Kushtia' => [23.90, 89.12],
        'Jhenaidah' => [23.54, 89.17], 'Magura' => [23.49, 89.42], 'Narail' => [23.17, 89.51], 'Chuadanga' => [23.64, 88.84], 'Meherpur' => [23.76, 88.63],
        'Barishal' => [22.70, 90.35], 'Patuakhali' => [22.36, 90.33], 'Bhola' => [22.69, 90.65], 'Pirojpur' => [22.58, 89.97], 'Jhalokati' => [22.64, 90.20],
        'Barguna' => [22.15, 90.12],
        'Sylhet' => [24.89, 91.87], 'Moulvibazar' => [24.48, 91.77], 'Habiganj' => [24.37, 91.42], 'Sunamganj' => [25.07, 91.40],
        'Rangpur' => [25.74, 89.25], 'Dinajpur' => [25.63, 88.64], 'Kurigram' => [25.81, 89.64], 'Gaibandha' => [25.33, 89.53], 'Nilphamari' => [25.93, 88.86],
        'Lalmonirhat' => [25.92, 89.45], 'Thakurgaon' => [26.03, 88.46], 'Panchagarh' => [26.33, 88.55],
        'Mymensingh' => [24.75, 90.41], 'Jamalpur' => [24.92, 89.95], 'Netrokona' => [24.88, 90.73], 'Sherpur' => [25.02, 90.02],
    ];

    /** Border events: once one is seen the parcel is past Benapole. */
    public const BORDER_EVENTS = ['border_crossing', 'customs_clearance', 'dispatched_to_destination'];

    public function points(Order $order): array
    {
        $district = (string) ($order->address['district'] ?? 'Dhaka');
        [$lat, $lng] = self::DISTRICTS[$district] ?? self::DISTRICTS['Dhaka'];
        $out = [];
        foreach (self::HUBS as $id => $h) {
            $out[] = ['id' => $id, 'label' => $h['label'], 'short' => $h['short'], 'lat' => $h['lat'], 'lng' => $h['lng']];
        }
        $out[] = ['id' => 'customer', 'label' => trim(($order->address['area'] ?? '').', '.$district, ', '), 'short' => $district, 'lat' => $lat, 'lng' => $lng];

        return $out;
    }

    /** Progress along the whole route (three legs of equal weight), current leg index (null before Kolkata) and stage. */
    public function progress(Order $order, ?SkydropEvent $last = null): array
    {
        $pastBorder = $last && in_array($last->event, self::BORDER_EVENTS, true);

        return match ($order->status) {
            'india_hub' => ['progress' => 0.0, 'leg' => 0, 'at' => 'kolkata'],
            'in_transit' => $pastBorder ? ['progress' => 0.45, 'leg' => 1, 'at' => 'border'] : ['progress' => 0.2, 'leg' => 0, 'at' => 'to_border'],
            'dhaka_hub' => ['progress' => 2 / 3, 'leg' => 2, 'at' => 'dhaka'],
            'out_for_delivery' => ['progress' => 0.83, 'leg' => 2, 'at' => 'to_customer'],
            'delivered' => ['progress' => 1.0, 'leg' => 2, 'at' => 'customer'],
            default => ['progress' => 0.0, 'leg' => null, 'at' => $order->status === 'cancelled' ? 'cancelled' : 'india'],
        };
    }

    /** Point at $t (0..1) along the polyline of $points (equal leg weights). */
    public static function along(array $points, float $t): array
    {
        $legs = count($points) - 1;
        $t = max(0, min(1, $t)) * $legs;
        $i = min($legs - 1, (int) floor($t));
        $f = $t - $i;
        $a = $points[$i];
        $b = $points[$i + 1];

        return ['lat' => round($a['lat'] + ($b['lat'] - $a['lat']) * $f, 5), 'lng' => round($a['lng'] + ($b['lng'] - $a['lng']) * $f, 5)];
    }

    public function eta(Order $order): ?array
    {
        $days = config('fulfilment.tracking.eta_days.'.$order->status);
        if (! $days || in_array($order->status, ['delivered', 'cancelled'], true)) {
            return null;
        }
        $base = Carbon::now();

        return ['from' => $base->copy()->addDays($days[0])->toDateString(), 'to' => $base->copy()->addDays($days[1])->toDateString()];
    }

    public function rider(Order $order): ?array
    {
        $loc = (array) $order->rider_location;
        if ($order->status !== 'out_for_delivery' || ! isset($loc['lat'], $loc['lng'], $loc['at'])) {
            return null;
        }
        $at = Carbon::parse($loc['at']);
        if ($at->lt(now()->subMinutes((int) config('fulfilment.tracking.rider_fresh_minutes', 10)))) {
            return null;
        }

        return ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng'], 'at' => $at->toIso8601String()];
    }

    public function toApi(Order $order): array
    {
        $order->loadMissing('events');
        $scans = SkydropEvent::query()->where('order_id', $order->id)->orderBy('at')->orderBy('id')->get();
        $lastScan = $scans->reject(fn ($e) => $e->event === 'rider_location')->last();
        $points = $this->points($order);
        $p = $this->progress($order, $lastScan);
        $rider = $this->rider($order);
        $legs = [['id' => 'india', 'from' => 'kolkata', 'to' => 'border', 'label' => 'Kolkata → Petrapole'],
            ['id' => 'border', 'from' => 'border', 'to' => 'dhaka', 'label' => 'Benapole → Dhaka hub'],
            ['id' => 'last_mile', 'from' => 'dhaka', 'to' => 'customer', 'label' => 'Dhaka hub → '.$points[3]['short']]];
        foreach ($legs as $i => $leg) {
            $legs[$i]['state'] = $order->status === 'delivered' || ($p['leg'] !== null && $i < $p['leg']) ? 'done'
                : ($p['leg'] === $i && $order->status !== 'india_hub' && $order->status !== 'dhaka_hub' ? 'current' : 'next');
        }

        $events = $order->events->map(fn ($e) => ['type' => 'status', 'status' => $e->status, 'note' => $e->note, 'at' => $e->at?->toIso8601String(), 'source' => $e->source])
            ->concat($scans->reject(fn ($e) => $e->event === 'rider_location')->map(fn ($e) => ['type' => 'scan', 'status' => $e->status, 'event' => $e->event,
                'note' => $e->note, 'at' => $e->at?->toIso8601String(), 'source' => 'skydrop', 'lat' => $e->lat, 'lng' => $e->lng]))
            ->sortBy('at')->values();

        return [
            'orderId' => $order->id,
            'status' => $order->status,
            'trackingCode' => (string) $order->tracking_code,
            'points' => $points,
            'legs' => $legs,
            'currentLeg' => $p['leg'],
            'stage' => $p['at'],
            'progress' => round($p['progress'], 3),
            'position' => $order->status === 'cancelled' ? null : ($rider ? ['lat' => $rider['lat'], 'lng' => $rider['lng']] : self::along($points, $p['progress'])),
            'eta' => $this->eta($order),
            'lastEvent' => $events->last(),
            'events' => $events->all(),
            'rider' => $rider,
            'liveMap' => Features::enabled('live_tracking_map'),
            'pod' => $order->status === 'delivered' ? ($order->pod ?: null) : null,
            'unboxingHold' => HubService::holdsShipping($order),
        ];
    }
}
