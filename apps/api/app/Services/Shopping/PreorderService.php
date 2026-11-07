<?php

namespace App\Services\Shopping;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PreorderRule;
use App\Models\Product;
use App\Models\ProductWatch;
use App\Models\User;
use App\Services\Cms\RestrictionService;
use App\Services\Commerce\AddressService;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Payments\PaymentService;
use App\Services\Messaging\Messenger;
use App\Services\PricingService;
use App\Support\Features;
use Illuminate\Support\Facades\DB;

/**
 * Pre-orders for out-of-stock products flagged by the admin (preorder_rules, per product or category).
 * The shopper pays a deposit (default 30%); the order is flagged orders.is_preorder and stays "placed"
 * (preorder_state = awaiting_stock) until stock returns, then we remind them to pay the balance.
 */
class PreorderService
{
    public function __construct(private PricingService $pricing, private CheckoutService $checkout) {}

    public function ruleFor(Product $p): ?PreorderRule
    {
        if (! Features::enabled('preorders')) {
            return null;
        }

        return PreorderRule::query()->where('active', true)->where('scope', 'product')->where('target', $p->id)->first()
            ?? PreorderRule::query()->where('active', true)->where('scope', 'category')->where('target', $p->category)
                ->where(fn ($q) => $q->whereNull('market')->orWhere('market', $p->market))->orderByRaw('market is null')->first();
    }

    public static function outOfStock(Product $p): bool
    {
        return ! $p->in_stock;
    }

    public function waitlistCount(string $productId): int
    {
        return ProductWatch::query()->where('product_id', $productId)->where('type', 'back_in_stock')->where('active', true)->count();
    }

    /** GET /products/{id}/preorder */
    public function info(Product $p, ?User $viewer = null): array
    {
        $rule = self::outOfStock($p) ? $this->ruleFor($p) : null;
        $price = $this->pricing->productBdt($p);
        $out = [
            'productId' => $p->id, 'inStock' => (bool) $p->in_stock, 'available' => (bool) $rule && ! $p->hidden,
            'waitlist' => Features::enabled('waitlist') ? $this->waitlistCount($p->id) : null,
            'watching' => $viewer ? ProductWatch::query()->where('user_id', $viewer->id)->where('product_id', $p->id)
                ->where('type', 'back_in_stock')->where('active', true)->exists() : false,
        ];
        if ($rule) {
            $out += [
                'depositPct' => (float) $rule->deposit_pct, 'priceBdt' => $price, 'depositBdt' => self::deposit($price, (float) $rule->deposit_pct),
                'etaDays' => $rule->eta_days, 'etaDate' => now()->addDays($rule->eta_days)->toDateString(), 'note' => $rule->note,
            ];
        }

        return $out;
    }

    public static function deposit(float $total, float $pct): int
    {
        return (int) ceil($total * max(1, min(100, $pct)) / 100);
    }

    /** POST /preorders: $data {productId, variant?, qty, addressId|address, payment, returnUrl?} */
    public function place(User $user, array $data): array
    {
        if ($user->blocked_at) {
            throw new CommerceException('This account has been blocked and cannot place orders. Please contact support.', 403, [], 'blocked');
        }
        $p = Product::query()->findOrFail($data['productId']);
        $rule = self::outOfStock($p) && ! $p->hidden ? $this->ruleFor($p) : null;
        if (! $rule) {
            throw new CommerceException($p->in_stock ? 'This item is in stock: add it to your cart instead.' : 'This item cannot be pre-ordered.', 422, [], 'not_preorderable');
        }
        $address = $this->address($user, $data);
        $items = [['productId' => $p->id, 'variant' => $data['variant'] ?? null, 'qty' => (int) ($data['qty'] ?? 1)]];
        $lines = $this->checkout->lines($items);
        app(RestrictionService::class)->assertOrderable($lines);
        $quote = $this->checkout->quote($lines, null, $user);
        $orders = app(OrderService::class);
        $fx = $this->pricing->fx();
        $pct = (float) $rule->deposit_pct;

        $order = DB::transaction(function () use ($user, $lines, $quote, $address, $data, $fx, $pct, $rule, $orders) {
            $order = Order::query()->create([
                'id' => $orders->newOrderId(), 'user_id' => $user->id, 'status' => 'placed',
                'payment_method' => $data['payment'], 'payment_status' => 'pending',
                'subtotal_bdt' => $quote['subtotal'], 'shipping_bdt' => $quote['shipping'], 'discount_bdt' => $quote['discount'],
                'total_bdt' => $quote['total'], 'paid_bdt' => 0, 'address' => $address->snapshot(), 'tracking_code' => $orders->newTrackingCode(),
                'fx_rate' => $fx, 'points_to_earn' => $quote['pointsToEarn'], 'return_url' => $data['returnUrl'] ?? null,
                'is_preorder' => true, 'preorder_state' => 'awaiting_stock', 'deposit_pct' => $pct,
                'deposit_bdt' => self::deposit($quote['total'], $pct), 'preorder_eta' => now()->addDays($rule->eta_days)->toDateString(),
            ]);
            foreach ($lines as $l) {
                OrderItem::query()->create([
                    'order_id' => $order->id, 'product_id' => $l['product_id'], 'market' => $l['market'], 'title' => mb_substr($l['title'], 0, 500),
                    'image' => $l['image'], 'placeholder' => $l['placeholder'], 'variant' => $l['variant'], 'qty' => $l['qty'],
                    'unit_bdt' => $l['unit_bdt'], 'unit_inr' => $l['unit_inr'], 'fx_rate' => $fx, 'weight_kg' => $l['weight_kg'],
                ]);
            }
            app(OrderWorkflow::class)->event($order, 'placed', 'customer',
                'Pre-order placed: '.rtrim(rtrim(number_format($pct, 2), '0'), '.').'% deposit now, balance when the item is back in stock');

            return $order;
        });

        // Also join the restock waitlist so the back-in-stock alert fires.
        ProductWatch::query()->updateOrCreate(['user_id' => $user->id, 'product_id' => $p->id, 'type' => 'back_in_stock'],
            ['active' => true, 'notified_at' => null, 'baseline_bdt' => $this->pricing->productBdt($p)]);

        app(Notifier::class)->orderPlaced($order);
        $payment = app(PaymentService::class)->start($order);

        return ['order' => $order->refresh(), 'payment' => $payment];
    }

    /**
     * Hourly: pre-orders whose items are back in stock move to balance_due and the shopper gets a pay link once;
     * fully paid ones become ready (the workflow then moves them on like any paid order).
     *
     * @return array{due: int, ready: int}
     */
    public function process(): array
    {
        $due = 0;
        $ready = 0;
        if (! Features::enabled('preorders')) {
            return compact('due', 'ready');
        }
        Order::query()->where('is_preorder', true)->whereIn('preorder_state', ['awaiting_stock', 'balance_due'])
            ->where('status', '!=', 'cancelled')->with(['items', 'user'])->chunkById(100, function ($orders) use (&$due, &$ready) {
                foreach ($orders as $o) {
                    if ($o->balanceBdt() <= 0) {
                        $o->forceFill(['preorder_state' => 'ready'])->save();
                        $ready++;

                        continue;
                    }
                    if ($o->preorder_state !== 'awaiting_stock' || (float) $o->paid_bdt <= 0) {
                        continue; // deposit not paid yet, or already reminded
                    }
                    $ids = $o->items->pluck('product_id')->all();
                    $back = Product::query()->whereIn('id', $ids)->where('in_stock', true)->where('hidden', false)->count() === count(array_unique($ids));
                    if (! $back) {
                        continue;
                    }
                    $o->forceFill(['preorder_state' => 'balance_due', 'balance_reminded_at' => now()])->save();
                    $this->remind($o);
                    $due++;
                }
            }, 'id');

        return compact('due', 'ready');
    }

    public function remind(Order $o): void
    {
        if (! $o->user) {
            return;
        }
        $link = Messenger::webUrl('/orders/'.$o->id.'?pay=1');
        $title = mb_strimwidth((string) ($o->items->first()?->title ?? 'Your pre-order'), 0, 40, '…');
        rescue(fn () => app(Notifier::class)->custom($o->user,
            "Good news: {$title} is back in stock. Pay the balance of BDT ".number_format($o->balanceBdt())." for order {$o->id} so we can buy it for you: {$link}", 'sms'), null, true);
    }

    private function address(User $user, array $data): Address
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

            return app(AddressService::class)->findOrCreate($user, $a);
        }
        throw CommerceException::field('addressId', 'A delivery address is required.');
    }
}
