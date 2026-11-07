<?php

namespace App\Services\Fulfilment;

use App\Models\Fulfilment\HubBox;
use App\Models\Fulfilment\HubReceipt;
use App\Models\Fulfilment\HubTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\OrderWorkflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kolkata hub: receive parcels from Indian marketplaces, record weight/dimensions/condition/photos,
 * move complete orders to india_hub, handle discrepancies and consolidate an order into one outbound box.
 */
class HubService
{
    /** Order statuses whose items can still arrive at the hub. */
    public const RECEIVABLE = ['placed', 'verified', 'purchased', 'india_hub'];

    public function __construct(private OrderWorkflow $workflow, private Notifier $notifier) {}

    /**
     * Items matching a scanned or typed code: marketplace order ref / AWB (purchase_ref), our order id,
     * our item id ("IT-123" or "123"), Skydrop tracking code or the source product id.
     *
     * @return Collection<int, OrderItem>
     */
    public function find(string $code): Collection
    {
        $code = trim($code);
        if ($code === '') {
            return collect();
        }
        $orders = Order::query()->goods()->whereIn('status', self::RECEIVABLE)->select('id');
        $itemId = preg_match('/^(?:IT-?)?(\d{1,10})$/i', $code, $m) ? (int) $m[1] : null;
        $upper = strtoupper($code);

        return OrderItem::query()
            ->whereIn('order_id', $orders)
            ->whereNull('cancelled_at')
            ->where(function ($q) use ($code, $upper, $itemId) {
                $q->where('purchase_ref', $code)
                    ->orWhere('order_id', $upper)
                    ->orWhere('product_id', $code)
                    ->orWhereIn('order_id', Order::query()->select('id')->where('tracking_code', $upper));
                if ($itemId) {
                    $q->orWhere('id', $itemId);
                }
            })
            ->orderBy('order_id')->orderBy('id')
            ->limit(50)
            ->get();
    }

    /** Active (not cancelled) items of an order. */
    public function activeItems(Order $order): Collection
    {
        return $order->items()->whereNull('cancelled_at')->get();
    }

    /** Per-store receiving progress: [market => [received, total]]. */
    public function progress(Order $order): array
    {
        return $this->activeItems($order)->groupBy('market')
            ->map(fn ($items) => ['received' => $items->whereNotNull('hub_received_at')->count(), 'total' => $items->count()])
            ->all();
    }

    /**
     * Records a parcel at the hub and marks the item received.
     *
     * @param  array{weight_kg: float|string, length_cm?: mixed, width_cm?: mixed, height_cm?: mixed, condition?: string, notes?: ?string, photos?: array, video?: ?string}  $data
     */
    public function receive(OrderItem $item, array $data, ?User $staff = null): HubReceipt
    {
        $order = Order::query()->findOrFail($item->order_id);
        if (! in_array($order->status, self::RECEIVABLE, true)) {
            throw new CommerceException("Order {$order->id} is {$order->status}; it cannot be received at the hub.", 409, [], 'not_receivable');
        }
        if ($item->cancelled_at) {
            throw new CommerceException('This item was cancelled.', 409, [], 'item_cancelled');
        }
        if ($item->hub_received_at) {
            throw new CommerceException('IT-'.$item->id.' was already received at the hub.', 409, [], 'already_received');
        }
        $condition = $data['condition'] ?? 'ok';
        if (! array_key_exists($condition, HubReceipt::CONDITIONS)) {
            throw CommerceException::field('condition', 'Choose ok, damaged or wrong item.');
        }
        $weight = (float) ($data['weight_kg'] ?? 0);
        if ($weight <= 0 || $weight > 500) {
            throw CommerceException::field('weight_kg', 'Enter the parcel weight in kg.');
        }
        $photos = array_values(array_filter((array) ($data['photos'] ?? [])));
        if ($order->unboxing && $photos === []) {
            throw CommerceException::field('photos', 'This order has paid unboxing: add photos of the opened item.');
        }

        $receipt = DB::transaction(function () use ($item, $order, $data, $staff, $condition, $weight, $photos) {
            $receipt = HubReceipt::query()->create([
                'order_item_id' => $item->id, 'order_id' => $order->id, 'staff_id' => $staff?->id, 'weight_kg' => $weight,
                'length_cm' => self::num($data['length_cm'] ?? null), 'width_cm' => self::num($data['width_cm'] ?? null),
                'height_cm' => self::num($data['height_cm'] ?? null), 'condition' => $condition,
                'notes' => $data['notes'] ?? null, 'photos' => $photos, 'video' => $data['video'] ?? null, 'received_at' => now(),
            ]);
            $item->forceFill(['hub_received_at' => now(), 'hub_condition' => $condition])->save();

            return $receipt;
        });

        if ($condition !== 'ok') {
            $this->discrepancy($order, $item, $receipt, $staff);
        }
        $this->checkComplete($order->refresh());

        return $receipt;
    }

    /** Damaged / wrong item: tell the customer and open a return-to-seller task. */
    public function discrepancy(Order $order, OrderItem $item, HubReceipt $receipt, ?User $staff = null): HubTask
    {
        $label = $receipt->condition === 'damaged' ? 'arrived damaged' : 'is not the item you ordered';
        $title = Str::limit($item->title, 60);
        $task = HubTask::query()->create([
            'type' => 'return_to_seller', 'status' => 'open', 'order_id' => $order->id, 'order_item_id' => $item->id,
            'hub_receipt_id' => $receipt->id, 'created_by' => $staff?->id,
            'note' => 'Hub check: '.HubReceipt::CONDITIONS[$receipt->condition].($receipt->notes ? ' — '.$receipt->notes : ''),
        ]);
        $this->workflow->event($order, $order->status, 'admin', "Hub check: \"{$title}\" {$label}. We are returning it to the seller.");
        $order->loadMissing('user');
        if ($order->user) {
            rescue(fn () => $this->notifier->custom($order->user,
                "Skydrop Retail: your item \"{$title}\" (order {$order->id}) {$label} at our Kolkata hub. We are returning it to the seller and will contact you about a replacement or refund.",
                'sms'), null, true);
        }

        return $task;
    }

    /** All active items received → measure, flag weight variance and move the order to india_hub. */
    public function checkComplete(Order $order): bool
    {
        $items = $this->activeItems($order);
        if ($items->isEmpty() || $items->contains(fn (OrderItem $i) => ! $i->hub_received_at)) {
            return false;
        }
        $this->measure($order, $items);
        if ($order->stageIndex() < $order->stageIndex('india_hub')) {
            $kg = number_format((float) $order->actual_weight_kg, 2);
            $this->workflow->transition($order, 'india_hub', 'admin', "All {$items->count()} item(s) received at the Kolkata hub ({$kg} kg)");
        }
        $order->refresh();
        if ($order->unboxing && ! $order->unboxing_status) {
            app(UnboxingService::class)->requestApproval($order);
        }

        return true;
    }

    /** Actual weight (sum of the latest receipt per item) and the weight-variance flag. */
    public function measure(Order $order, ?Collection $items = null): void
    {
        $items ??= $this->activeItems($order);
        $receipts = HubReceipt::query()->whereIn('order_item_id', $items->pluck('id'))->orderBy('id')->get()->keyBy('order_item_id');
        $actual = round($receipts->sum('weight_kg'), 3);
        $estimate = round($items->sum(fn (OrderItem $i) => $i->weight_kg * $i->qty), 3);
        $limit = FulfilmentSettings::float('hub.weight_variance_pct');
        $variance = $estimate > 0 ? round(($actual - $estimate) / $estimate * 100, 1) : 0.0;
        $flag = null;
        if ($estimate > 0 && $variance > $limit) {
            $perKg = (float) config('retail.pricing.shipping.per_kg_bdt', 0);
            $flag = ['estimateKg' => $estimate, 'actualKg' => $actual, 'variancePct' => $variance, 'limitPct' => $limit,
                'extraBdt' => (int) (ceil($perKg * ($actual - $estimate) / 10) * 10), 'resolution' => null, 'at' => now()->toIso8601String()];
        }
        // A flag staff already resolved is kept as is.
        $resolved = ! empty(((array) $order->weight_flag)['resolution']);
        $order->forceFill(['actual_weight_kg' => $actual, 'weight_flag' => $resolved ? $order->weight_flag : $flag])->save();
    }

    /** Staff decision on a weight flag: absorb the extra shipping or charge the customer. */
    public function resolveWeight(Order $order, string $resolution): Order
    {
        $flag = (array) $order->weight_flag;
        if (! $flag) {
            return $order;
        }
        $flag['resolution'] = $resolution;
        $flag['resolvedAt'] = now()->toIso8601String();
        $order->forceFill(['weight_flag' => $flag])->save();
        if ($resolution === 'charge' && ($flag['extraBdt'] ?? 0) > 0) {
            app(PriceAlertService::class)->chargeCustomer($order, (int) $flag['extraBdt'], 'Extra shipping: the parcel weighs '.$flag['actualKg'].' kg (estimated '.$flag['estimateKg'].' kg)');
        }

        return $order->refresh();
    }

    /** Groups every received, not yet boxed item of an order into one outbound box. */
    public function consolidate(Order $order, ?User $staff = null): HubBox
    {
        $items = $this->activeItems($order);
        $receipts = HubReceipt::query()->whereIn('order_item_id', $items->pluck('id'))->whereNull('box_id')
            ->orderByDesc('id')->get()->unique('order_item_id');
        if ($receipts->isEmpty()) {
            $existing = HubBox::query()->where('order_id', $order->id)->latest('id')->first();
            if ($existing) {
                return $existing;
            }
            throw new CommerceException('No received items to pack for this order.', 409, [], 'nothing_to_pack');
        }

        return DB::transaction(function () use ($order, $staff, $receipts, $items) {
            $box = HubBox::query()->create([
                'order_id' => $order->id, 'code' => 'BX-'.$order->id.'-'.(HubBox::query()->where('order_id', $order->id)->count() + 1),
                'weight_kg' => round($receipts->sum('weight_kg'), 3),
                'item_count' => (int) $items->whereIn('id', $receipts->pluck('order_item_id'))->sum('qty'),
                'staff_id' => $staff?->id, 'packed_at' => now(),
            ]);
            HubReceipt::query()->whereIn('id', $receipts->pluck('id'))->update(['box_id' => $box->id]);
            $this->workflow->event($order, $order->status, 'admin', 'Packed into one box for Bangladesh ('.$box->item_count.' item(s), '.number_format($box->weight_kg, 2).' kg)');

            return $box;
        });
    }

    /** True while the order must not leave the hub (customer approval of the unboxing pending). */
    public static function holdsShipping(Order $order): bool
    {
        return $order->unboxing && in_array($order->unboxing_status, [null, UnboxingService::AWAITING, UnboxingService::PROBLEM], true)
            && $order->stageIndex() <= $order->stageIndex('india_hub');
    }

    private static function num(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : (float) $v;
    }
}
