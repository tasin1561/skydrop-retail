<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public const STATUSES = ['placed', 'verified', 'purchased', 'india_hub', 'in_transit', 'dhaka_hub', 'out_for_delivery', 'delivered', 'cancelled'];

    /** Shopper-facing label for the payment method (web `Order.payment`). */
    public const PAYMENT_LABELS = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'card' => 'Card (SSLCommerz)', 'advance50' => '50% advance, rest on delivery'];

    protected function casts(): array
    {
        return [
            'address' => 'array', 'subtotal_bdt' => 'float', 'shipping_bdt' => 'float', 'discount_bdt' => 'float',
            'total_bdt' => 'float', 'paid_bdt' => 'float', 'fx_rate' => 'float', 'coupon_discount_bdt' => 'float',
            'points_redeemed' => 'integer', 'points_discount_bdt' => 'float', 'points_to_earn' => 'integer',
            'points_earned' => 'integer', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('at')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(OrderVerification::class);
    }

    public function supportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class);
    }

    public function stageIndex(?string $status = null): int
    {
        $i = array_search($status ?? $this->status, self::STATUSES, true);

        return $i === false ? -1 : $i;
    }

    public function canCancel(): bool
    {
        return $this->status !== 'cancelled' && $this->stageIndex() < $this->stageIndex('purchased');
    }

    public function balanceBdt(): float
    {
        return max(0, round($this->total_bdt - $this->paid_bdt, 2));
    }

    public function paymentLabel(): string
    {
        $label = self::PAYMENT_LABELS[$this->payment_method] ?? $this->payment_method;
        if ($this->payment_method === 'advance50' && $this->payment_via) {
            $label .= ' ('.(self::PAYMENT_LABELS[$this->payment_via] ?? $this->payment_via).')';
        }

        return $label;
    }

    /** Web `Order` shape plus paymentStatus, timeline and points fields. */
    public function toApi(): array
    {
        $this->loadMissing(['items', 'events']);

        return [
            'id' => $this->id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'items' => $this->items->map->toApi()->values()->all(),
            'address' => $this->address,
            'payment' => $this->paymentLabel(),
            'paymentMethod' => $this->payment_method,
            'paymentStatus' => $this->payment_status,
            'paidBdt' => (int) round($this->paid_bdt),
            'dueOnDelivery' => (int) round($this->balanceBdt()),
            'subtotal' => (int) round($this->subtotal_bdt),
            'shipping' => (int) round($this->shipping_bdt),
            'discount' => (int) round($this->discount_bdt),
            'couponCode' => $this->coupon_code,
            'couponDiscount' => (int) round($this->coupon_discount_bdt),
            'pointsRedeemed' => (int) $this->points_redeemed,
            'pointsDiscount' => (int) round($this->points_discount_bdt),
            'pointsToEarn' => (int) $this->points_to_earn,
            'pointsEarned' => (int) $this->points_earned,
            'total' => (int) round($this->total_bdt),
            'status' => $this->status,
            'trackingCode' => (string) $this->tracking_code,
            'canCancel' => $this->canCancel(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'timeline' => $this->events->map(fn (OrderEvent $e) => [
                'status' => $e->status, 'at' => $e->at?->toIso8601String(), 'note' => $e->note,
            ])->values()->all(),
        ];
    }
}
