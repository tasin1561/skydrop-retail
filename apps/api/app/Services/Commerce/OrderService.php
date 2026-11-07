<?php

namespace App\Services\Commerce;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderVerification;
use App\Models\Product;
use App\Models\User;
use App\Services\Cms\RestrictionService;
use App\Services\Commerce\Payments\PaymentService;
use App\Services\Commerce\Verify\PriceVerifier;
use App\Services\Growth\AffiliateService;
use App\Services\Money\MoneyRules;
use App\Services\Money\WalletPayments;
use App\Services\PricingService;
use App\Services\Shopping\OrderHooks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private CheckoutService $checkout,
        private PriceVerifier $verifier,
        private PricingService $pricing,
        private AddressService $addresses,
        private PointsService $points,
        private PaymentService $payments,
        private OrderWorkflow $workflow,
        private Notifier $notifier,
    ) {}

    /**
     * Creates an order from a validated request payload (see OrderController::store).
     *
     * @return array{order: Order, payment: array}
     */
    public function place(User $user, array $data): array
    {
        if ($user->blocked_at) {
            throw new CommerceException('This account has been blocked and cannot place orders. Please contact support.', 403, [], 'blocked');
        }
        $items = array_values($data['items']);
        app(OrderLimits::class)->assertCustomer($user); // System → Limits: orders per day, unpaid orders (422)
        $address = $this->resolveAddress($user, $data);
        $coupon = $this->checkout->coupon($data['coupon'] ?? null);
        [$method, $via] = $this->paymentMethod($data);

        // 1. Live re-verification. Never trust client prices.
        $lines = $this->checkout->lines($items);
        app(RestrictionService::class)->assertOrderable($lines); // items that cannot ship to Bangladesh → 422
        $check = $this->verifier->verify($items);
        $byId = collect($check['results'])->keyBy('productId');
        $tolerance = (float) config('retail.checkout.price_tolerance', 0.03);
        $changed = [];
        $failed = [];
        foreach ($lines as $k => $line) {
            $r = $byId[$line['product_id']] ?? null;
            if (! $r || ! $r['ok'] || ! ($r['deliverable'] ?? true)) {
                $failed[] = ['productId' => $line['product_id'], 'title' => $line['title'], 'reason' => $r['reason'] ?? 'unavailable'];

                continue;
            }
            $fresh = (float) ($r['freshBdt'] ?? $line['unit_bdt']);
            $client = (float) ($line['client_bdt'] ?? $fresh);
            if ($client <= 0 || abs($fresh - $client) / $client > $tolerance) {
                $changed[] = ['productId' => $line['product_id'], 'variant' => $line['variant'], 'title' => $line['title'],
                    'oldBdt' => (int) round($client), 'freshBdt' => (int) round($fresh)];
            }
            $lines[$k]['unit_bdt'] = $fresh;
            $lines[$k]['provider'] = $r['provider'] ?? null;
            $lines[$k]['verify'] = $r;
        }
        if ($failed) {
            throw new CommerceException('Some items cannot be ordered right now.', 422, ['code' => 'unavailable', 'unavailable' => $failed, 'results' => $check['results']]);
        }
        if ($changed) {
            throw new CommerceException('Prices changed since you added these items. Please review the new prices.', 409,
                ['code' => 'price_changed', 'prices' => $changed, 'results' => $check['results']]);
        }

        // INR prices may have been refreshed by the live check.
        $inr = Product::query()->whereIn('id', array_column($lines, 'product_id'))->pluck('price_inr', 'id');
        $fx = $this->pricing->fx();

        // 2. Totals (coupon + points) on verified prices.
        $quote = $this->checkout->quote($lines, $coupon, $user, (int) ($data['redeemPoints'] ?? 0),
            MoneyRules::opts(['payment' => $method] + $data, true) // money: Plus, wallet, card EMI
            + ['unboxing' => (bool) ($data['unboxing'] ?? false)]); // fulfilment: paid unboxing
        app(OrderLimits::class)->assertMinimum($quote); // System → Limits: min order value (422)

        // 3. Persist.
        $order = DB::transaction(function () use ($user, $lines, $quote, $coupon, $address, $method, $via, $fx, $inr, $data, $check) {
            $order = Order::query()->create([
                'id' => $this->newOrderId(), 'user_id' => $user->id, 'status' => 'placed',
                'payment_method' => $method, 'payment_via' => $via, 'payment_status' => 'pending',
                'subtotal_bdt' => $quote['subtotal'], 'shipping_bdt' => $quote['shipping'], 'discount_bdt' => $quote['discount'],
                'coupon_discount_bdt' => $quote['couponDiscount'], 'total_bdt' => $quote['total'], 'paid_bdt' => 0,
                'coupon_code' => $coupon?->code, 'address' => $address->snapshot(), 'tracking_code' => $this->newTrackingCode(),
                'fx_rate' => $fx, 'points_redeemed' => $quote['pointsRedeemed'], 'points_discount_bdt' => $quote['pointsDiscount'],
                'points_to_earn' => $quote['pointsToEarn'], 'return_url' => $data['returnUrl'] ?? null,
                'attribution' => $data['attribution'] ?? null, // marketing attribution (App\Services\Tracking\Attribution)
                'membership_discount_bdt' => $quote['membershipDiscount'] ?? 0, 'emi_charge_bdt' => $quote['emiCharge'] ?? 0,
                'unboxing' => (bool) ($quote['unboxing']['selected'] ?? false), 'unboxing_fee_bdt' => $quote['unboxingFee'] ?? 0, // fulfilment
                'emi' => ! empty($quote['emi']['eligible']) ? array_intersect_key($quote['emi'], array_flip(['tenure', 'bank', 'bankName', 'ratePct', 'monthlyBdt'])) : null,
            ]);
            foreach ($lines as $l) {
                OrderItem::query()->create([
                    'order_id' => $order->id, 'product_id' => $l['product_id'], 'market' => $l['market'], 'title' => mb_substr($l['title'], 0, 500),
                    'image' => $l['image'], 'placeholder' => $l['placeholder'], 'variant' => $l['variant'], 'qty' => $l['qty'],
                    'unit_bdt' => $l['unit_bdt'], 'unit_inr' => (float) ($inr[$l['product_id']] ?? $l['unit_inr']), 'fx_rate' => $fx,
                    'weight_kg' => $l['weight_kg'],
                ]);
                OrderVerification::query()->create([
                    'order_id' => $order->id, 'product_id' => $l['product_id'], 'seen_bdt' => $l['client_bdt'] ?? $l['unit_bdt'],
                    'fresh_bdt' => $l['unit_bdt'], 'deliverable' => (bool) ($l['verify']['deliverable'] ?? true), 'ok' => true,
                    'provider' => $l['provider'] ? mb_substr((string) $l['provider'], 0, 30) : null, 'raw' => $l['verify'] ?? null,
                    'verified_at' => now(),
                ]);
            }
            $this->workflow->event($order, 'placed', 'customer', 'Order placed; prices verified live at '.($check['checkedAt'] ?? now()->toIso8601String()));
            if ($coupon) {
                Coupon::query()->whereKey($coupon->id)->increment('used');
            }
            $this->points->redeem($user, $order, $quote['pointsRedeemed']);
            app(WalletPayments::class)->payFromWallet($user, $order, (int) ($quote['walletApplied'] ?? 0)); // money: store credit
            app(OrderHooks::class)->placed($order, $quote, $data); // Shopping: bundle saving, group-buy shipping split

            return $order;
        });

        // Growth module: affiliate attribution (ref code, last click). Never blocks the order.
        rescue(fn () => app(AffiliateService::class)->attribute($order, $data['ref'] ?? null), null, true);

        $this->notifier->orderPlaced($order);
        $payment = $this->payments->start($order);

        return ['order' => $order->refresh(), 'payment' => $payment];
    }

    private function resolveAddress(User $user, array $data): Address
    {
        if (! empty($data['addressId'])) {
            $a = $user->addresses()->find($data['addressId']);
            if (! $a) {
                throw CommerceException::field('addressId', 'Choose one of your saved addresses.');
            }

            return $a;
        }
        if (! empty($data['address']) && is_array($data['address'])) {
            $a = $data['address'];
            unset($a['id'], $a['isDefault']);

            return $this->addresses->findOrCreate($user, $a);
        }
        throw CommerceException::field('addressId', 'A delivery address is required.');
    }

    /** @return array{0: string, 1: ?string} [method, wallet for advance50] */
    private function paymentMethod(array $data): array
    {
        $m = $data['payment'] === 'half' ? 'advance50' : $data['payment'];
        if ($m === 'advance50') {
            $via = $data['paymentVia'] ?? 'bkash';

            return [$m, $via];
        }

        return [$m, null];
    }

    public function newOrderId(): string
    {
        do {
            $id = 'SR-'.Str::upper(Str::random(6));
        } while (! preg_match('/^SR-[A-Z0-9]{6}$/', $id) || Order::query()->whereKey($id)->exists());

        return $id;
    }

    public function newTrackingCode(): string
    {
        do {
            $code = 'SD'.str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
        } while (Order::query()->where('tracking_code', $code)->exists());

        return $code;
    }
}
