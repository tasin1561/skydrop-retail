<?php

namespace App\Filament\Support;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\PointsLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bridge from admin actions to the commerce and catalog modules. Each call prefers the module's own service
 * (OrderWorkflow, PointsService, Notifier, Skydrop client, payment refunds, FetchProductJob) and only falls back
 * to a minimal local implementation while that service is not available yet.
 */
class Ops
{
    public const WORKFLOW = 'App\\Services\\Commerce\\OrderWorkflow';

    public const POINTS = 'App\\Services\\Commerce\\PointsService';

    public const NOTIFIER = 'App\\Services\\Commerce\\Notifier';

    public const FETCH_JOB = 'App\\Jobs\\Catalog\\FetchProductJob';

    /** Candidate Skydrop clients from the commerce module, first match wins. */
    public const SKYDROP = ['App\\Services\\Commerce\\SkydropClient', 'App\\Services\\Commerce\\Skydrop', 'App\\Services\\Skydrop\\SkydropClient'];

    /** Candidate payment services that can refund, first match wins. */
    public const PAYMENTS = ['App\\Services\\Commerce\\PaymentService', 'App\\Services\\Commerce\\Payments', 'App\\Services\\Payments\\PaymentService'];

    public static function transition(Order $order, string $status, ?string $note = null): Order
    {
        if (! in_array($status, Order::STATUSES, true)) {
            throw new RuntimeException("Unknown status {$status}");
        }

        if (class_exists(self::WORKFLOW) && method_exists(self::WORKFLOW, 'transition')) {
            $workflow = app(self::WORKFLOW);
            $result = $workflow->transition($order, $status, 'admin', $note);

            return $result instanceof Order ? $result : $order->refresh();
        }

        // Fallback until the commerce OrderWorkflow lands: plain status change plus a timeline event.
        DB::transaction(function () use ($order, $status, $note) {
            $order->forceFill(['status' => $status] + match ($status) {
                'delivered' => ['delivered_at' => now()],
                'cancelled' => ['cancelled_at' => now()],
                default => [],
            })->save();
            OrderEvent::query()->create(['order_id' => $order->id, 'status' => $status, 'note' => $note, 'source' => 'admin', 'at' => now()]);
        });

        return $order->refresh();
    }

    /** Next forward status in the fulfilment pipeline, or null at the end. */
    public static function nextStatus(Order $order): ?string
    {
        $flow = array_values(array_diff(Order::STATUSES, ['cancelled']));
        $i = array_search($order->status, $flow, true);

        return $i === false || ! isset($flow[$i + 1]) ? null : $flow[$i + 1];
    }

    /** @param  array<int|string, string>  $refs  order_item id => marketplace order reference */
    public static function markPurchased(Order $order, array $refs, ?string $note = null): Order
    {
        foreach ($order->items as $item) {
            if (filled($refs[$item->id] ?? null)) {
                $item->forceFill(['purchase_ref' => trim($refs[$item->id])])->save();
            }
        }

        return $order->status === 'purchased' ? $order : self::transition($order, 'purchased', $note ?: 'Bought from the source stores');
    }

    /** Create the Skydrop consignment and move the order to the Kolkata hub stage. */
    public static function handToSkydrop(Order $order): Order
    {
        $client = collect(self::SKYDROP)->first(fn ($c) => class_exists($c));
        if ($client) {
            $svc = app($client);
            foreach (['createConsignment', 'consign', 'create'] as $method) {
                if (method_exists($svc, $method)) {
                    $res = $svc->{$method}($order);
                    $order->refresh();
                    if (is_array($res)) {
                        $order->forceFill(array_filter([
                            'skydrop_consignment_id' => $order->skydrop_consignment_id ?: ($res['consignment_id'] ?? $res['id'] ?? null),
                            'tracking_code' => $order->tracking_code ?: ($res['tracking_code'] ?? $res['trackingCode'] ?? null),
                        ]))->save();
                    }
                    break;
                }
            }
        } elseif (config('retail.skydrop.mode', 'mock') === 'mock') {
            $order->forceFill([
                'skydrop_consignment_id' => $order->skydrop_consignment_id ?: 'MOCK-'.Str::upper(Str::random(8)),
                'tracking_code' => $order->tracking_code ?: 'SKY'.random_int(10000000, 99999999),
            ])->save();
        } else {
            throw new RuntimeException('Skydrop client is not available yet (commerce module).');
        }

        return $order->stageIndex() < $order->stageIndex('india_hub')
            ? self::transition($order, 'india_hub', 'Handed to Skydrop, consignment '.$order->skydrop_consignment_id)
            : $order;
    }

    /** Refund part or all of a payment. Returns the refund reference. */
    public static function refund(Payment $payment, float $amount, string $reason, ?string $ref = null): string
    {
        $amount = round(min($amount, $payment->amount_bdt - (float) $payment->refunded_bdt), 2);
        if ($amount <= 0) {
            throw new RuntimeException('Nothing left to refund on this payment.');
        }

        $svc = collect(self::PAYMENTS)->first(fn ($c) => class_exists($c) && method_exists($c, 'refund'));
        if ($svc) {
            $res = app($svc)->refund($payment, $amount, $reason);
            $payment->refresh();

            return is_string($res) ? $res : (string) ($payment->refund_ref ?? 'refunded');
        }

        // Manual refund (staff sends money back through the gateway's merchant portal and records it here).
        $ref = $ref ?: 'MANUAL-'.now()->format('ymdHis');
        DB::transaction(function () use ($payment, $amount, $reason, $ref) {
            $refunded = (float) $payment->refunded_bdt + $amount;
            $payment->remember(['refunds' => [...($payment->payload['refunds'] ?? []), ['amount' => $amount, 'reason' => $reason, 'ref' => $ref, 'at' => now()->toIso8601String()]]]);
            $payment->forceFill([
                'refunded_bdt' => $refunded,
                'refund_ref' => $ref,
                'status' => $refunded >= $payment->amount_bdt ? 'refunded' : $payment->status,
            ])->save();

            $order = $payment->order;
            if ($order) {
                $paid = max(0, (float) $order->paid_bdt - $amount);
                $order->forceFill(['paid_bdt' => $paid, 'payment_status' => $paid <= 0 ? 'refunded' : 'partial'])->save();
                OrderEvent::query()->create(['order_id' => $order->id, 'status' => $order->status, 'note' => "Refund ৳{$amount} ({$reason}) ref {$ref}", 'source' => 'admin', 'at' => now()]);
            }
        });

        return $ref;
    }

    public static function adjustPoints(User $user, int $points, string $note): void
    {
        if (class_exists(self::POINTS) && method_exists(self::POINTS, 'adjust')) {
            app(self::POINTS)->adjust($user, $points, $note);

            return;
        }

        DB::transaction(function () use ($user, $points, $note) {
            PointsLedger::query()->create(['user_id' => $user->id, 'type' => 'adjust', 'points' => $points, 'note' => $note]);
            $user->forceFill(['points_balance' => (int) $user->points_balance + $points])->save();
        });
    }

    /** Send an SMS / notification through the commerce Notifier. Returns false when no notifier exists yet. */
    public static function notify(User $user, string $message, string $channel = 'sms'): bool
    {
        if (! class_exists(self::NOTIFIER)) {
            return false;
        }
        $n = app(self::NOTIFIER);
        foreach ([$channel, 'send', 'notify', 'sms'] as $method) {
            if (method_exists($n, $method)) {
                $method === 'sms' ? $n->sms($user->phone ?? $user, $message) : $n->{$method}($user, $message);

                return true;
            }
        }

        return false;
    }

    /** Queue a product refresh from the source. Returns false when the catalog job does not exist yet. */
    public static function refreshProduct(string $productId): bool
    {
        if (! class_exists(self::FETCH_JOB)) {
            return false;
        }
        dispatch(new (self::FETCH_JOB)($productId));

        return true;
    }
}
