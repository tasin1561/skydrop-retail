<?php

namespace App\Services\Fulfilment;

use App\Models\Fulfilment\HubReceipt;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\OrderWorkflow;
use App\Support\Features;

/**
 * Paid unboxing: the Kolkata hub opens the parcel, photographs/videos it and the customer approves before it ships.
 * The approval wait is a flag on the order (orders.unboxing_status), not a workflow status.
 */
class UnboxingService
{
    public const AWAITING = 'awaiting_customer_approval';

    public const APPROVED = 'approved';

    public const AUTO = 'auto_approved';

    public const PROBLEM = 'problem';

    public const PROBLEM_REASONS = ['damaged', 'wrong_item', 'not_as_described', 'size_issue'];

    public function __construct(private OrderWorkflow $workflow, private Notifier $notifier) {}

    public function enabled(): bool
    {
        return Features::enabled('unboxing');
    }

    /**
     * Fee for a quote. $groups are the quote groups ({market, count, ...}).
     *
     * @return array{available: bool, selected: bool, feeBdt: int, per: string, unitBdt: int, waived: bool}
     */
    public function fee(array $groups, bool $selected, ?User $user = null): array
    {
        $unit = (int) round(FulfilmentSettings::float('unboxing.fee_bdt'));
        $per = FulfilmentSettings::get('unboxing.per') === 'item' ? 'item' : 'shipment';
        $units = $per === 'item' ? array_sum(array_column($groups, 'count')) : count($groups);
        $waived = $this->waived($user);
        $available = $this->enabled();
        $fee = $available && $selected && ! $waived ? $unit * $units : 0;

        return ['available' => $available, 'selected' => $available && $selected, 'feeBdt' => $fee, 'per' => $per, 'unitBdt' => $unit, 'waived' => $waived];
    }

    /** Membership benefit `free_unboxing` (membership module, when installed). */
    public function waived(?User $user): bool
    {
        $svc = 'App\\Services\\Money\\MembershipService';
        if (! $user || ! class_exists($svc) || ! method_exists($svc, 'benefitsFor')) {
            return false;
        }

        return (bool) rescue(fn () => (app($svc)->benefitsFor($user)['free_unboxing'] ?? false), false, false);
    }

    /** Called once every item is received (with photos): the customer reviews before shipping. */
    public function requestApproval(Order $order): Order
    {
        $order->forceFill(['unboxing_status' => self::AWAITING, 'unboxing_requested_at' => now()])->save();
        $this->workflow->event($order, $order->status, 'admin', 'Unboxed and photographed at the Kolkata hub. Waiting for your approval.');
        $order->loadMissing('user');
        if ($order->user) {
            $hours = (int) FulfilmentSettings::get('unboxing.auto_approve_hours');
            $link = rtrim((string) config('retail.web_url'), '/').'/orders/'.$order->id.'/unboxing';
            rescue(fn () => $this->notifier->custom($order->user,
                "Skydrop Retail: we opened your order {$order->id} at our Kolkata hub. See the photos and approve it before it ships: {$link} (auto-approved after {$hours}h).",
                'sms'), null, true);
        }

        return $order;
    }

    public function approve(Order $order, string $source = 'customer'): Order
    {
        if ($order->unboxing_status !== self::AWAITING) {
            throw new CommerceException('This unboxing is not waiting for approval.', 409, [], 'not_awaiting');
        }
        $auto = $source === 'system';
        $order->forceFill(['unboxing_status' => $auto ? self::AUTO : self::APPROVED, 'unboxing_decided_at' => now()])->save();
        $this->workflow->event($order, $order->status, $auto ? 'system' : 'customer',
            $auto ? 'Unboxing auto-approved (no response). Shipping to Bangladesh.' : 'You approved the unboxing photos. Shipping to Bangladesh.');

        return $order->refresh();
    }

    /** Customer reports a problem: opens a return/replacement case at the India stage. */
    public function reportProblem(Order $order, User $user, string $reason, ?string $note = null): Order
    {
        if ($order->unboxing_status !== self::AWAITING) {
            throw new CommerceException('This unboxing is not waiting for approval.', 409, [], 'not_awaiting');
        }
        if (! in_array($reason, self::PROBLEM_REASONS, true)) {
            throw CommerceException::field('reason', 'Choose what is wrong.');
        }
        $order->forceFill(['unboxing_status' => self::PROBLEM, 'unboxing_decided_at' => now()])->save();
        app(ReturnService::class)->openIndiaCase($order, $user, $reason, $note);
        $this->workflow->event($order, $order->status, 'customer', 'You reported a problem with the unboxing. Our team will contact you.');

        return $order->refresh();
    }

    /** Auto-approves unboxings older than the configured window. Returns how many were approved. */
    public function autoApproveDue(): int
    {
        $hours = (int) FulfilmentSettings::get('unboxing.auto_approve_hours');
        $n = 0;
        Order::query()->where('unboxing_status', self::AWAITING)->where('unboxing_requested_at', '<=', now()->subHours($hours))
            ->each(function (Order $o) use (&$n) {
                $this->approve($o, 'system');
                $n++;
            });

        return $n;
    }

    /** Customer-facing payload for GET /orders/{id}/unboxing. */
    public function toApi(Order $order): array
    {
        $order->loadMissing('items');
        $receipts = HubReceipt::query()->where('order_id', $order->id)->orderBy('id')->get()->keyBy('order_item_id');
        $hours = (int) FulfilmentSettings::get('unboxing.auto_approve_hours');

        return [
            'orderId' => $order->id,
            'selected' => (bool) $order->unboxing,
            'feeBdt' => (int) round((float) $order->unboxing_fee_bdt),
            'status' => $order->unboxing_status,
            'requestedAt' => $order->unboxing_requested_at?->toIso8601String(),
            'autoApproveAt' => $order->unboxing_requested_at?->copy()->addHours($hours)->toIso8601String(),
            'decidedAt' => $order->unboxing_decided_at?->toIso8601String(),
            'reasons' => self::PROBLEM_REASONS,
            'items' => $order->items->whereNull('cancelled_at')->map(function ($i) use ($receipts) {
                $r = $receipts[$i->id] ?? null;

                return ['id' => $i->id, 'title' => $i->title, 'variant' => $i->variant, 'qty' => (int) $i->qty, 'image' => $i->image,
                    'market' => $i->market, 'condition' => $r?->condition, 'note' => $r?->notes,
                    'photos' => $r ? $r->photoUrls() : [], 'video' => $r?->videoUrl()];
            })->values()->all(),
        ];
    }
}
