<?php

namespace App\Jobs\Commerce;

use App\Models\Order;
use App\Services\Commerce\Skydrop\SkydropClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

// Books the two-leg Skydrop consignment once the order is bought in India.
class CreateSkydropConsignment implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public string $orderId) {}

    public function handle(SkydropClient $skydrop): void
    {
        $order = Order::query()->find($this->orderId);
        if (! $order || $order->skydrop_consignment_id || $order->status === 'cancelled') {
            return;
        }
        $r = $skydrop->createConsignment($order);
        $order->forceFill(['skydrop_consignment_id' => $r['consignmentId'], 'tracking_code' => $r['trackingCode'] ?: $order->tracking_code])->save();
        Log::info('[skydrop] consignment '.$r['consignmentId'].' for '.$order->id);
    }
}
