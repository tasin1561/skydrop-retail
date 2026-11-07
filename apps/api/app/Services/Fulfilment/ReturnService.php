<?php

namespace App\Services\Fulfilment;

use App\Filament\Support\Ops;
use App\Models\Fulfilment\ReturnRequest;
use App\Models\Fulfilment\Upload;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Money\MembershipService;
use App\Support\Features;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Returns and replacements: requested → approved/rejected → pickup_scheduled → received → refunded/replaced/credited.
 * Cases at the India stage (unboxing problem) skip the pickup: the item is still at the Kolkata hub.
 */
class ReturnService
{
    /** Allowed next statuses per status. */
    public const NEXT = [
        'requested' => ['approved', 'rejected'],
        'approved' => ['pickup_scheduled', 'received', 'refunded', 'replaced', 'credited'],
        'pickup_scheduled' => ['received'],
        'received' => ['refunded', 'replaced', 'credited'],
    ];

    public function __construct(private Notifier $notifier) {}

    public function enabled(): bool
    {
        return Features::enabled('returns');
    }

    /** @return array<int, string> */
    public function reasons(): array
    {
        $r = array_keys(ReturnRequest::REASONS);

        return FulfilmentSettings::bool('returns.changed_mind') ? $r : array_values(array_diff($r, ['changed_mind']));
    }

    /** Can this order be returned now, and which item quantities are left? */
    public function eligibility(Order $order): array
    {
        $order->loadMissing('items');
        $days = (int) FulfilmentSettings::get('returns.window_days');
        $endsAt = $order->delivered_at?->copy()->addDays($days);
        $reason = null;
        if (! $this->enabled()) {
            $reason = 'disabled';
        } elseif ($order->status !== 'delivered' || ! $order->delivered_at) {
            $reason = 'not_delivered';
        } elseif ($endsAt->isPast()) {
            $reason = 'window_closed';
        }
        $taken = $this->takenQty($order);
        $items = $order->items->whereNull('cancelled_at')->map(fn (OrderItem $i) => [
            'id' => $i->id, 'productId' => $i->product_id, 'title' => $i->title, 'variant' => $i->variant, 'image' => $i->image,
            'market' => $i->market, 'qty' => (int) $i->qty, 'returnableQty' => max(0, (int) $i->qty - ($taken[$i->id] ?? 0)),
            'unitBdt' => (int) round($i->unit_bdt),
        ])->values()->all();
        if (! $reason && collect($items)->sum('returnableQty') === 0) {
            $reason = 'already_requested';
        }

        $order->loadMissing('user');
        $free = $this->freeReturns($order->user);

        return [
            'eligible' => $reason === null, 'reason' => $reason, 'windowDays' => $days, 'windowEndsAt' => $endsAt?->toIso8601String(),
            'reasons' => $this->reasons(), 'resolutions' => array_keys(ReturnRequest::RESOLUTIONS), 'items' => $items,
            // Return shipping: charged only for feeReasons, waived for Skydrop Plus members with free returns.
            'returnFee' => ['amountBdt' => $free ? 0 : $this->shippingFee(), 'reasons' => array_values(array_intersect($this->feeReasons(), $this->reasons())), 'waived' => $free],
        ];
    }

    /** Quantities already in a non-rejected return, per order item id. */
    private function takenQty(Order $order): array
    {
        $taken = [];
        foreach (ReturnRequest::query()->where('order_id', $order->id)->where('status', '!=', 'rejected')->get() as $r) {
            foreach ((array) $r->items as $line) {
                $taken[(int) $line['orderItemId']] = ($taken[(int) $line['orderItemId']] ?? 0) + (int) $line['qty'];
            }
        }

        return $taken;
    }

    /**
     * Customer return request after delivery.
     *
     * @param  array{items: array<int, array{orderItemId: int, qty?: int}>, reason: string, note?: ?string, photos?: array<int, int>, resolution: string}  $data
     */
    public function create(Order $order, User $user, array $data): ReturnRequest
    {
        $e = $this->eligibility($order);
        if (! $e['eligible']) {
            $msg = ['not_delivered' => 'Returns open once the order is delivered.', 'window_closed' => "The {$e['windowDays']}-day return window has closed.",
                'already_requested' => 'Every item of this order is already in a return.', 'disabled' => 'Returns are not available right now.'][$e['reason']] ?? 'Not returnable.';
            throw new CommerceException($msg, 422, [], $e['reason']);
        }
        if (! in_array($data['reason'], $e['reasons'], true)) {
            throw CommerceException::field('reason', 'This reason is not accepted for returns.');
        }
        if (! array_key_exists($data['resolution'], ReturnRequest::RESOLUTIONS)) {
            throw CommerceException::field('resolution', 'Choose refund, replacement or store credit.');
        }
        $avail = collect($e['items'])->keyBy('id');
        $lines = [];
        foreach ($data['items'] as $line) {
            $id = (int) $line['orderItemId'];
            $qty = (int) ($line['qty'] ?? ($avail[$id]['returnableQty'] ?? 0));
            if (! isset($avail[$id]) || $qty < 1 || $qty > $avail[$id]['returnableQty']) {
                throw CommerceException::field('items', 'Choose items (and quantities) from this order that are not already being returned.');
            }
            $lines[] = ['orderItemId' => $id, 'qty' => $qty];
        }
        if (! $lines) {
            throw CommerceException::field('items', 'Choose at least one item.');
        }
        $photos = array_values(array_unique(array_map('intval', (array) ($data['photos'] ?? []))));
        if ($photos && Upload::query()->whereIn('id', $photos)->where('user_id', $user->id)->count() !== count($photos)) {
            throw CommerceException::field('photos', 'Upload the photos again.');
        }
        if (in_array($data['reason'], ['damaged', 'wrong_item'], true) && ! $photos) {
            throw CommerceException::field('photos', 'Add at least one photo of the problem.');
        }

        $value = $this->valueOf($order, $lines);
        $fee = min($value, $this->returnFee($user, $data['reason']));
        $r = ReturnRequest::query()->create([
            'order_id' => $order->id, 'user_id' => $user->id, 'stage' => 'delivered', 'items' => $lines, 'reason' => $data['reason'],
            'note' => $data['note'] ?? null, 'photos' => $photos, 'resolution' => $data['resolution'], 'status' => 'requested',
            'refund_bdt' => round($value - $fee, 2), 'return_fee_bdt' => $fee, 'priority' => $this->priority($user),
            'timeline' => [self::entry('requested', 'Return requested: '.ReturnRequest::REASONS[$data['reason']]
                .($fee > 0 ? ' (return shipping ৳'.number_format($fee).' deducted from the refund)' : ''), 'customer')],
        ]);
        StaffAlerts::send("Return requested on {$order->id}", ReturnRequest::REASONS[$data['reason']].' · wants '.ReturnRequest::RESOLUTIONS[$data['resolution']], null, 'warning');

        return $r;
    }

    /** India-stage case from an unboxing problem (all active items, replacement by default). */
    public function openIndiaCase(Order $order, User $user, string $reason, ?string $note = null, string $resolution = 'replacement'): ReturnRequest
    {
        $lines = $order->items()->whereNull('cancelled_at')->get()->map(fn ($i) => ['orderItemId' => $i->id, 'qty' => (int) $i->qty])->values()->all();
        $r = ReturnRequest::query()->create([
            'order_id' => $order->id, 'user_id' => $user->id, 'stage' => 'india', 'items' => $lines, 'reason' => $reason, 'note' => $note,
            'photos' => [], 'resolution' => $resolution, 'status' => 'requested', 'refund_bdt' => $this->valueOf($order, $lines),
            'priority' => $this->priority($user), // India-stage cases never pay return shipping
            'timeline' => [self::entry('requested', 'Problem reported after the unboxing at the Kolkata hub', 'customer')],
        ]);
        StaffAlerts::send("Unboxing problem on {$order->id}", (ReturnRequest::REASONS[$reason] ?? $reason).($note ? ': '.$note : ''), null);

        return $r;
    }

    // ---- Skydrop Plus benefits (MembershipService::benefitsFor) ----

    /** Return shipping fee (BDT) for a delivered-stage return: only for customer-choice reasons; 0 for free_returns members. */
    public function returnFee(?User $user, string $reason): float
    {
        if (! in_array($reason, $this->feeReasons(), true) || $this->freeReturns($user)) {
            return 0.0;
        }

        return $this->shippingFee();
    }

    public function shippingFee(): float
    {
        return max(0.0, round(FulfilmentSettings::float('returns.shipping_fee_bdt'), 2));
    }

    /** @return list<string> */
    public function feeReasons(): array
    {
        return array_values((array) FulfilmentSettings::get('returns.fee_reasons', ['changed_mind', 'size_issue']));
    }

    public function freeReturns(?User $user): bool
    {
        return (bool) (self::benefits($user)['free_returns'] ?? false);
    }

    public function priority(?User $user): bool
    {
        return (bool) (self::benefits($user)['priority_support'] ?? false);
    }

    private static function benefits(?User $user): ?array
    {
        return $user ? rescue(fn () => app(MembershipService::class)->benefitsFor($user), null, false) : null;
    }

    /** Value of the returned lines (item price, BDT). */
    public function valueOf(Order $order, array $lines): float
    {
        $items = $order->items()->get()->keyBy('id');

        return round(collect($lines)->sum(fn ($l) => isset($items[$l['orderItemId']]) ? $items[$l['orderItemId']]->unit_bdt * $l['qty'] : 0), 2);
    }

    public function canMove(ReturnRequest $r, string $to): bool
    {
        if (! in_array($to, self::NEXT[$r->status] ?? [], true)) {
            return false;
        }
        // Delivered-stage cases must be received back before money/replacement; India cases are already at the hub.
        if ($r->status === 'approved' && in_array($to, ['refunded', 'replaced', 'credited'], true)) {
            return $r->stage === 'india';
        }

        return true;
    }

    public function move(ReturnRequest $r, string $to, ?string $note = null, string $by = 'admin', array $extra = []): ReturnRequest
    {
        if (! $this->canMove($r, $to)) {
            throw new CommerceException("A return that is {$r->status} cannot move to {$to}.", 409, [], 'invalid_transition');
        }
        $r->forceFill($extra + [
            'status' => $to,
            'timeline' => [...(array) $r->timeline, self::entry($to, $note, $by)],
            'resolved_at' => in_array($to, ReturnRequest::FINAL, true) ? now() : $r->resolved_at,
        ])->save();
        $this->notifyCustomer($r, $to, $note);

        return $r->refresh();
    }

    public function approve(ReturnRequest $r, ?string $note = null): ReturnRequest
    {
        return $this->move($r, 'approved', $note ?: 'Return approved');
    }

    public function reject(ReturnRequest $r, string $note): ReturnRequest
    {
        return $this->move($r, 'rejected', $note);
    }

    /** TODO(skydrop): book a Skydrop reverse pickup once the API exposes it; for now staff schedule it manually. */
    public function schedulePickup(ReturnRequest $r, \DateTimeInterface $at, ?string $note = null): ReturnRequest
    {
        return $this->move($r, 'pickup_scheduled', $note ?: 'Skydrop pickup on '.$at->format('j M Y'), 'admin', ['pickup_at' => $at]);
    }

    public function markReceived(ReturnRequest $r, ?string $note = null): ReturnRequest
    {
        return $this->move($r, 'received', $note ?: 'Item received back', 'admin', ['received_at' => now()]);
    }

    /** Refund through the original payment (PaymentService gateways); any rest goes to the wallet when available. */
    public function refund(ReturnRequest $r, ?float $amount = null, ?string $note = null): ReturnRequest
    {
        $order = Order::query()->findOrFail($r->order_id);
        $amount = round(min($amount ?? $r->refund_bdt, (float) $order->paid_bdt), 2);
        if ($amount <= 0) {
            throw new CommerceException('Nothing was paid on this order to refund. Use store credit instead.', 409, [], 'nothing_paid');
        }
        $remaining = $amount;
        $refs = [];
        foreach ($order->payments()->where('status', 'paid')->where('gateway', '!=', 'skydrop_cod')->get() as $p) {
            $part = min($remaining, (float) $p->amount_bdt - (float) $p->refunded_bdt);
            if ($part <= 0) {
                continue;
            }
            try {
                $refs[] = Ops::refund($p, $part, "Return #{$r->id}");
                $remaining = round($remaining - $part, 2);
            } catch (Throwable $e) {
                report($e);
            }
            if ($remaining <= 0) {
                break;
            }
        }
        if ($remaining > 0) {
            if (! $this->walletAvailable()) {
                throw new CommerceException('The gateway could not refund ৳'.number_format($remaining).'. Refund it in the merchant portal (Payments → Refund) and mark the return refunded.', 409, [], 'refund_failed');
            }
            $this->wallet()->credit($r->user, $remaining, 'refund', "Return #{$r->id} (order {$order->id})", $order->id);
            $refs[] = 'wallet';
        }

        return $this->move($r, 'refunded', $note ?: 'Refunded ৳'.number_format($amount).' ('.implode(', ', $refs).')', 'admin', ['refund_bdt' => $amount]);
    }

    public function credit(ReturnRequest $r, ?float $amount = null, ?string $note = null): ReturnRequest
    {
        if (! $this->walletAvailable()) {
            throw new CommerceException('Store credit (wallet) is not available.', 409, [], 'wallet_unavailable');
        }
        $amount = round($amount ?? $r->refund_bdt, 2);
        $this->wallet()->credit($r->user, $amount, 'refund', "Return #{$r->id} (order {$r->order_id})", $r->order_id);

        return $this->move($r, 'credited', $note ?: '৳'.number_format($amount).' added to your Skydrop wallet', 'admin', ['refund_bdt' => $amount]);
    }

    /** Creates a zero-charge replacement order for the returned items, linked to the original. */
    public function replace(ReturnRequest $r, ?string $note = null): ReturnRequest
    {
        $order = Order::query()->with('items')->findOrFail($r->order_id);
        $ids = app(OrderService::class);
        $replacement = DB::transaction(function () use ($order, $r, $ids) {
            $new = Order::query()->create([
                'id' => $ids->newOrderId(), 'user_id' => $order->user_id, 'status' => 'placed', 'payment_method' => $order->payment_method,
                'payment_via' => $order->payment_via, 'payment_status' => 'paid', 'subtotal_bdt' => 0, 'shipping_bdt' => 0, 'discount_bdt' => 0,
                'total_bdt' => 0, 'paid_bdt' => 0, 'address' => $order->address, 'tracking_code' => $ids->newTrackingCode(),
                'fx_rate' => $order->fx_rate, 'replacement_of' => $order->id, 'notes' => "Replacement for {$order->id} (return #{$r->id})",
            ]);
            $items = $order->items->keyBy('id');
            foreach ((array) $r->items as $line) {
                $src = $items[$line['orderItemId']] ?? null;
                if (! $src) {
                    continue;
                }
                OrderItem::query()->create([
                    'order_id' => $new->id, 'product_id' => $src->product_id, 'market' => $src->market, 'title' => $src->title, 'image' => $src->image,
                    'placeholder' => $src->placeholder, 'variant' => $src->variant, 'qty' => (int) $line['qty'], 'unit_bdt' => 0,
                    'unit_inr' => $src->unit_inr, 'fx_rate' => $src->fx_rate, 'weight_kg' => $src->weight_kg,
                ]);
            }
            app(OrderWorkflow::class)->event($new, 'placed', 'admin', "Free replacement for order {$order->id}");

            return $new;
        });
        app(OrderWorkflow::class)->transition($replacement, 'verified', 'admin', 'Replacement approved; no charge');

        return $this->move($r, 'replaced', $note ?: "Replacement order {$replacement->id} created at no charge", 'admin', ['replacement_order_id' => $replacement->id]);
    }

    public function walletAvailable(): bool
    {
        return class_exists('App\\Services\\Money\\WalletService') && Features::enabled('wallet');
    }

    private function wallet(): object
    {
        return app('App\\Services\\Money\\WalletService');
    }

    private function notifyCustomer(ReturnRequest $r, string $status, ?string $note): void
    {
        $r->loadMissing('user');
        if (! $r->user) {
            return;
        }
        $text = "Skydrop Retail: your return #{$r->id} for order {$r->order_id} is now ".strtolower(ReturnRequest::STATUSES[$status] ?? $status).($note ? ". {$note}" : '.');
        rescue(fn () => $this->notifier->custom($r->user, $text, 'sms'), null, true);
    }

    private static function entry(string $status, ?string $note, string $by): array
    {
        return ['status' => $status, 'at' => now()->toIso8601String(), 'note' => $note, 'by' => $by];
    }
}
