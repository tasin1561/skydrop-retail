<?php

namespace App\Services\Commerce;

use App\Jobs\Commerce\CreateSkydropConsignment;
use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Support\Facades\DB;

/**
 * Order status machine (used by the API, Skydrop webhooks and the Filament admin):
 * placed -> verified -> purchased -> india_hub -> in_transit -> dhaka_hub -> out_for_delivery -> delivered; cancelled.
 * Forward moves may skip stages (e.g. a Skydrop scan arrives late); backward moves are rejected.
 */
class OrderWorkflow
{
    public const FLOW = ['placed', 'verified', 'purchased', 'india_hub', 'in_transit', 'dhaka_hub', 'out_for_delivery', 'delivered'];

    public const SOURCES = ['system', 'skydrop', 'admin', 'customer'];

    public const NOTIFY = ['purchased', 'out_for_delivery', 'delivered', 'cancelled'];

    public const DEFAULT_NOTES = [
        'placed' => 'Order placed',
        'verified' => 'Live price and stock verified with the store',
        'purchased' => 'Bought from the store in India',
        'india_hub' => 'Received at Skydrop Kolkata hub',
        'in_transit' => 'Crossing the border with Skydrop',
        'dhaka_hub' => 'Received at Skydrop Dhaka hub',
        'out_for_delivery' => 'Out for delivery with a Skydrop rider',
        'delivered' => 'Delivered',
        'cancelled' => 'Order cancelled',
    ];

    public function __construct(private Notifier $notifier, private PointsService $points) {}

    /** Statuses an order may move to next (for admin UIs). */
    public function allowedNext(Order $order): array
    {
        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            return [];
        }
        $i = array_search($order->status, self::FLOW, true);
        $next = array_slice(self::FLOW, $i + 1);
        if ($order->canCancel()) {
            $next[] = 'cancelled';
        }

        return $next;
    }

    public function canTransition(Order $order, string $to): bool
    {
        if ($to === 'cancelled') {
            return $order->canCancel();
        }
        $from = array_search($order->status, self::FLOW, true);
        $target = array_search($to, self::FLOW, true);

        return $from !== false && $target !== false && $target > $from;
    }

    /**
     * Moves an order to $status, records a timeline event and runs side effects.
     * Cancelling refunds online payments and redeemed points (use cancel() for customer-initiated cancels).
     */
    public function transition(Order $order, string $status, string $source = 'system', ?string $note = null, ?\DateTimeInterface $at = null): Order
    {
        if (! in_array($status, Order::STATUSES, true)) {
            throw CommerceException::field('status', "Unknown status {$status}.");
        }
        if (! in_array($source, self::SOURCES, true)) {
            $source = 'system';
        }
        if ($order->status === $status) {
            return $order; // idempotent (duplicate webhook)
        }
        if (! $this->canTransition($order, $status)) {
            throw new CommerceException("An order that is {$order->status} cannot move to {$status}.", 409, [], 'invalid_transition');
        }

        DB::transaction(function () use ($order, $status, $source, $note, $at) {
            $order->status = $status;
            if ($status === 'delivered') {
                $order->delivered_at = $at ?? now();
            }
            if ($status === 'cancelled') {
                $order->cancelled_at = $at ?? now();
            }
            $order->save();
            $this->event($order, $status, $source, $note, $at);
        });

        $this->sideEffects($order, $status);

        return $order->refresh();
    }

    public function event(Order $order, string $status, string $source, ?string $note = null, ?\DateTimeInterface $at = null): OrderEvent
    {
        return OrderEvent::query()->create([
            'order_id' => $order->id, 'status' => $status, 'source' => $source,
            'note' => $note ?? (self::DEFAULT_NOTES[$status] ?? null), 'at' => $at ?? now(),
        ]);
    }

    /** Customer cancel: only before purchase; refunds online payments and points. */
    public function cancel(Order $order, string $source = 'customer', ?string $reason = null): Order
    {
        if (! $order->canCancel()) {
            throw new CommerceException('This order can no longer be cancelled because the items are already bought in India.', 409, [], 'not_cancellable');
        }

        return $this->transition($order, 'cancelled', $source, $reason ? 'Cancelled: '.$reason : ($source === 'customer' ? 'Cancelled by you' : 'Order cancelled'));
    }

    /** Called after a payment succeeds: verified once paid (or advance paid). */
    public function afterPayment(Order $order): void
    {
        $order->refresh();
        if ($order->status === 'placed' && in_array($order->payment_status, ['paid', 'partial'], true)) {
            $this->transition($order, 'verified', 'system', 'Payment received; live price and stock verified');
        }
    }

    private function sideEffects(Order $order, string $status): void
    {
        if ($status === 'purchased' && ! $order->skydrop_consignment_id) {
            CreateSkydropConsignment::dispatch($order->id);
        }
        if ($status === 'delivered') {
            $this->points->earn($order);
        }
        if ($status === 'cancelled') {
            app(Payments\PaymentService::class)->refundAll($order, 'Order cancelled');
            $this->points->refund($order);
            if ($order->tracking_code && $order->skydrop_consignment_id) {
                rescue(fn () => app(Skydrop\SkydropClient::class)->cancel($order), null, true);
            }
            $order->refresh();
        }
        if (in_array($status, self::NOTIFY, true)) {
            $this->notifier->statusChanged($order, $status);
        }
    }
}
