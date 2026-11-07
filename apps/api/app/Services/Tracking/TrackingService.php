<?php

namespace App\Services\Tracking;

use App\Jobs\Tracking\SendTrackingEvent;
use App\Models\Order;
use App\Models\TrackingEvent;
use App\Services\Tracking\Drivers\DelivroDriver;
use App\Services\Tracking\Drivers\Ga4Driver;
use App\Services\Tracking\Drivers\MetaDriver;
use App\Services\Tracking\Drivers\TikTokDriver;
use App\Services\Tracking\Drivers\TrackingDriver;
use App\Support\Features;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fans a normalised event out to the destinations chosen in settings (tracking.driver = off | delivro | direct | both).
 * Each destination gets one tracking_events row (unique event_id + destination = server-side dedupe) and one queued job.
 */
class TrackingService
{
    public const DRIVERS = ['delivro' => DelivroDriver::class, 'meta' => MetaDriver::class, 'ga4' => Ga4Driver::class, 'tiktok' => TikTokDriver::class];

    public function driver(string $name): TrackingDriver
    {
        return app(self::DRIVERS[$name]);
    }

    /** @return array<int, string> destination names enabled by the driver setting */
    public function destinations(): array
    {
        if (! Features::enabled('tracking')) {
            return []; // Admin → Features → "Pixels & server-side tracking" is off
        }

        return match (TrackingSettings::driver()) {
            'delivro' => ['delivro'],
            'direct' => ['meta', 'ga4', 'tiktok'],
            'both' => ['delivro', 'meta', 'ga4', 'tiktok'],
            default => [],
        };
    }

    /**
     * Queues the event for every enabled, configured destination that supports it.
     *
     * @return array<int, TrackingEvent> rows created now (already-sent duplicates are skipped)
     */
    public function dispatch(array $event, ?string $orderId = null): array
    {
        $rows = [];
        foreach ($this->destinations() as $name) {
            $driver = $this->driver($name);
            if (! $driver->configured() || ! $driver->supports($event['event'])) {
                continue;
            }
            try {
                $row = TrackingEvent::query()->firstOrCreate(
                    ['event_id' => $event['event_id'], 'destination' => $name],
                    ['event' => $event['event'], 'order_id' => $orderId ?? ($event['order_id'] ?? null), 'status' => 'queued', 'payload' => $event],
                );
            } catch (UniqueConstraintViolationException) {
                continue; // a concurrent request queued the same event
            }
            if (! $row->wasRecentlyCreated) {
                continue;
            }
            $rows[] = $row;
            SendTrackingEvent::dispatch($row->id)->afterCommit();
        }

        return $rows;
    }

    /** Purchase / Delivered for an order. Respects the shopper's "Essential only" consent captured at checkout. */
    public function orderEvent(string $orderId, string $event = 'Purchase'): void
    {
        try {
            if (! $this->destinations()) {
                return;
            }
            $order = Order::query()->with(['items', 'user'])->find($orderId);
            if (! $order || $order->status === 'cancelled' || ($order->attribution['consent'] ?? null) === 'essential') {
                return;
            }
            $this->dispatch(EventBuilder::fromOrder($order, $event), $order->id);
        } catch (Throwable $e) {
            // Tracking must never break the order flow.
            Log::warning('[tracking] order event failed: '.$e->getMessage(), ['order' => $orderId, 'event' => $event]);
        }
    }
}
