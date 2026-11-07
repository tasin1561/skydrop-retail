<?php

namespace App\Services\Money;

use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\Payments\PaymentService;
use App\Services\Commerce\Phone;

/**
 * Gift card and Skydrop Plus purchases reuse the normal order + payment pipeline: an `orders` row with
 * kind = gift_card | membership (no items, no tracking code, hidden from GET /orders), paid through
 * PaymentService::start() like any order. OrderWorkflow::afterPayment() calls fulfil() once paid.
 */
class DigitalOrders
{
    public const KINDS = ['gift_card', 'membership'];

    public const LABELS = ['gift_card' => 'Skydrop gift card', 'membership' => 'Skydrop Plus membership'];

    public function __construct(private PaymentService $payments) {}

    /** @return array{order: Order, payment: array} */
    public function create(User $user, string $kind, float $amount, string $method, array $meta, ?string $returnUrl): array
    {
        $order = $this->createOrder($user, $kind, $amount, $method, $meta, $returnUrl);

        return ['order' => $order, 'payment' => $this->payments->start($order)];
    }

    /** The unpaid order only (start the payment after the surrounding transaction commits). */
    public function createOrder(User $user, string $kind, float $amount, string $method, array $meta, ?string $returnUrl): Order
    {
        if ($user->blocked_at) {
            throw new CommerceException('This account has been blocked. Please contact support.', 403, [], 'blocked');
        }
        if (! in_array($method, PaymentService::WALLETS, true)) {
            throw CommerceException::field('payment', 'Choose bKash, Nagad or card.');
        }
        $orders = app(OrderService::class);
        return Order::query()->create([
            'id' => $orders->newOrderId(), 'user_id' => $user->id, 'kind' => $kind, 'status' => 'placed',
            'payment_method' => $method, 'payment_status' => 'pending',
            'subtotal_bdt' => $amount, 'shipping_bdt' => 0, 'discount_bdt' => 0, 'total_bdt' => $amount, 'paid_bdt' => 0,
            'address' => ['name' => $user->name, 'phone' => $user->phone ? (Phone::normalize($user->phone) ?? $user->phone) : null],
            'fx_rate' => 0, 'return_url' => $returnUrl, 'meta' => $meta,
        ]);
    }

    /** Called once the order is paid. */
    public function fulfil(Order $order): void
    {
        match ($order->kind) {
            'gift_card' => app(GiftCardService::class)->activateFromOrder($order),
            'membership' => app(MembershipService::class)->activateFromOrder($order),
            default => null,
        };
    }
}
