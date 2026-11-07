<?php

namespace App\Services\Money;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Notifier;
use App\Services\Messaging\Messenger;
use App\Support\Features;
use Illuminate\Support\Facades\DB;

/**
 * Skydrop Plus. Plans and their benefits are edited in the admin (membership_plans.benefits JSON).
 * Renewal is MANUAL: BD gateways (bKash/Nagad/SSLCommerz) have no reliable recurring billing, so members get a
 * reminder with a pay link before renews_at and pay again through the normal payment flow.
 */
class MembershipService
{
    /** Benefit keys with their type and default. */
    public const BENEFITS = [
        'free_shipping' => ['bool', false],
        'free_shipping_threshold_bdt' => ['int', 0], // free shipping per store parcel at/above this subtotal (0 = off)
        'shipping_discount_pct' => ['float', 0],
        'points_multiplier' => ['float', 1],
        'extra_discount_pct' => ['float', 0],
        'extra_discount_cap_bdt' => ['int', 0], // per order, 0 = no cap
        'free_unboxing' => ['bool', false],
        'priority_support' => ['bool', false],
        'early_flash_sale_access_minutes' => ['int', 0],
        'monthly_wallet_credit_bdt' => ['int', 0],
        'free_returns' => ['bool', false],
    ];

    public function __construct(private WalletService $wallet) {}

    public function enabled(): bool
    {
        return Features::enabled('membership');
    }

    /** @return array<string, bool|int|float> */
    public static function normalize(array $b): array
    {
        $out = [];
        foreach (self::BENEFITS as $k => [$type, $default]) {
            $v = $b[$k] ?? $default;
            $out[$k] = match ($type) {
                'bool' => (bool) $v, 'int' => max(0, (int) $v), default => max(0, (float) $v),
            };
        }
        $out['shipping_discount_pct'] = min(100, $out['shipping_discount_pct']);
        $out['extra_discount_pct'] = min(100, $out['extra_discount_pct']);
        $out['points_multiplier'] = max(1, min(10, $out['points_multiplier'] ?: 1));

        return $out;
    }

    /** camelCase benefits for clients. */
    public static function benefitsApi(array $b): array
    {
        return [
            'freeShipping' => $b['free_shipping'], 'freeShippingThresholdBdt' => $b['free_shipping_threshold_bdt'],
            'shippingDiscountPct' => $b['shipping_discount_pct'], 'pointsMultiplier' => $b['points_multiplier'],
            'extraDiscountPct' => $b['extra_discount_pct'], 'extraDiscountCapBdt' => $b['extra_discount_cap_bdt'],
            'freeUnboxing' => $b['free_unboxing'], 'prioritySupport' => $b['priority_support'],
            'earlyFlashSaleAccessMinutes' => $b['early_flash_sale_access_minutes'], 'monthlyWalletCreditBdt' => $b['monthly_wallet_credit_bdt'],
            'freeReturns' => $b['free_returns'],
        ];
    }

    /** Human-readable benefit list for plan cards. @return list<string> */
    public static function describe(array $b): array
    {
        $l = [];
        if ($b['free_shipping']) {
            $l[] = 'Free Skydrop shipping on every order';
        } elseif ($b['free_shipping_threshold_bdt'] > 0) {
            $l[] = 'Free shipping on store parcels from ৳'.number_format($b['free_shipping_threshold_bdt']);
        }
        if (! $b['free_shipping'] && $b['shipping_discount_pct'] > 0) {
            $l[] = self::num($b['shipping_discount_pct']).'% off shipping';
        }
        if ($b['extra_discount_pct'] > 0) {
            $l[] = self::num($b['extra_discount_pct']).'% extra off items'.($b['extra_discount_cap_bdt'] > 0 ? ' (up to ৳'.number_format($b['extra_discount_cap_bdt']).' per order)' : '');
        }
        if ($b['points_multiplier'] > 1) {
            $l[] = self::num($b['points_multiplier']).'× Skydrop Points';
        }
        if ($b['monthly_wallet_credit_bdt'] > 0) {
            $l[] = '৳'.number_format($b['monthly_wallet_credit_bdt']).' wallet credit every month';
        }
        if ($b['early_flash_sale_access_minutes'] > 0) {
            $l[] = 'Flash sales '.$b['early_flash_sale_access_minutes'].' minutes early';
        }
        if ($b['free_unboxing']) {
            $l[] = 'Free unboxing video at the Kolkata hub';
        }
        if ($b['free_returns']) {
            $l[] = 'Free returns';
        }
        if ($b['priority_support']) {
            $l[] = 'Priority support';
        }

        return $l;
    }

    private static function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2), '0'), '.');
    }

    /** @return \Illuminate\Support\Collection<int, MembershipPlan> */
    public function plans()
    {
        return MembershipPlan::query()->where('active', true)->orderBy('sort')->orderBy('price_bdt')->get();
    }

    /** Current membership (paid through a future date), if any. */
    public function current(?User $user): ?Membership
    {
        if (! $user) {
            return null;
        }

        return Membership::query()->with('plan')->where('user_id', $user->id)->whereIn('status', ['active', 'cancelled'])
            ->where('renews_at', '>', now())->latest('renews_at')->first();
    }

    /**
     * THE single source of membership benefits (checkout quote, orders, unboxing, flash sales, support…).
     * Null when the user is not a current member or the "membership" feature is off.
     *
     * @return array<string, mixed>|null normalized benefits + plan: {id, name, slug}
     */
    public function benefitsFor(?User $user): ?array
    {
        if (! $user || ! $this->enabled()) {
            return null;
        }
        $m = $this->current($user);
        if (! $m || ! $m->plan) {
            return null;
        }

        return self::normalize($m->plan->benefits ?? []) + ['plan' => ['id' => $m->plan->id, 'name' => $m->plan->name, 'slug' => $m->plan->slug]];
    }

    /** The plan used for "You'd save ৳X with Plus" (highlighted, else cheapest active). */
    public function nudgePlan(): ?MembershipPlan
    {
        $plans = $this->plans();

        return $plans->firstWhere('highlight', true) ?? $plans->first();
    }

    /**
     * Applies benefits to quote figures.
     *
     * @param  array<int, array{market: string, subtotal: int, shipping: int}>  $groups
     * @return array{shipping: int, items: int, total: int, lines: list<array{key: string, label: string, amountBdt: int}>}
     */
    public function apply(array $b, array $groups, int $itemsAfterDiscounts, int $shippingAfterCoupon): array
    {
        $lines = [];
        $ship = 0;
        if ($shippingAfterCoupon > 0) {
            if ($b['free_shipping']) {
                $ship = $shippingAfterCoupon;
                $lines[] = ['key' => 'free_shipping', 'label' => 'Free shipping', 'amountBdt' => $ship];
            } else {
                $left = $shippingAfterCoupon;
                if ($b['free_shipping_threshold_bdt'] > 0) {
                    $free = array_sum(array_map(fn ($g) => $g['subtotal'] >= $b['free_shipping_threshold_bdt'] ? $g['shipping'] : 0, $groups));
                    $free = min($left, $free);
                    if ($free > 0) {
                        $lines[] = ['key' => 'free_shipping', 'label' => 'Free shipping over ৳'.number_format($b['free_shipping_threshold_bdt']), 'amountBdt' => $free];
                        $left -= $free;
                        $ship += $free;
                    }
                }
                if ($b['shipping_discount_pct'] > 0 && $left > 0) {
                    $off = (int) round($left * $b['shipping_discount_pct'] / 100);
                    if ($off > 0) {
                        $lines[] = ['key' => 'shipping_discount', 'label' => self::num($b['shipping_discount_pct']).'% off shipping', 'amountBdt' => $off];
                        $ship += $off;
                    }
                }
            }
        }
        $items = 0;
        if ($b['extra_discount_pct'] > 0 && $itemsAfterDiscounts > 0) {
            $items = (int) round($itemsAfterDiscounts * $b['extra_discount_pct'] / 100);
            if ($b['extra_discount_cap_bdt'] > 0) {
                $items = min($items, $b['extra_discount_cap_bdt']);
            }
            if ($items > 0) {
                $lines[] = ['key' => 'extra_discount', 'label' => self::num($b['extra_discount_pct']).'% Plus discount', 'amountBdt' => $items];
            }
        }

        return ['shipping' => $ship, 'items' => $items, 'total' => $ship + $items, 'lines' => $lines];
    }

    // ---- purchase, renewal, admin ----------------------------------------------------------------

    public function plan(int|string $idOrSlug): MembershipPlan
    {
        $p = MembershipPlan::query()->where('active', true)->where(fn ($q) => $q->where('slug', (string) $idOrSlug)
            ->orWhere('id', is_numeric($idOrSlug) ? (int) $idOrSlug : 0))->first();
        if (! $p) {
            throw CommerceException::field('plan', 'This plan is not available.');
        }

        return $p;
    }

    /** Payment succeeded for a membership order: start or extend. Idempotent per order. */
    public function activateFromOrder(Order $order): ?Membership
    {
        $planId = (int) ($order->meta['plan_id'] ?? 0);
        $plan = MembershipPlan::query()->find($planId);
        if (! $plan || ! $order->user) {
            return null;
        }
        $existing = Membership::query()->where('user_id', $order->user_id)->get()
            ->first(fn (Membership $m) => collect($m->payment_refs ?? [])->contains('orderId', $order->id));
        if ($existing) {
            return $existing;
        }

        return $this->extendOrStart($order->user, $plan, 1, ['orderId' => $order->id, 'amountBdt' => (int) round($order->total_bdt), 'source' => 'payment']);
    }

    /** Starts a membership or extends the current one by $periods plan periods (renewals stack on the paid-through date). */
    public function extendOrStart(User $user, MembershipPlan $plan, int $periods, array $ref, ?string $note = null): Membership
    {
        return DB::transaction(function () use ($user, $plan, $periods, $ref, $note) {
            $m = Membership::query()->where('user_id', $user->id)->lockForUpdate()->latest('id')->first();
            $months = $plan->months() * max(1, $periods);
            if ($m && $m->renews_at && $m->renews_at->isFuture() && $m->status !== 'expired') {
                $m->renews_at = $m->renews_at->copy()->addMonthsNoOverflow($months);
            } else {
                $m ??= new Membership(['user_id' => $user->id]);
                $m->started_at = now();
                $m->renews_at = now()->addMonthsNoOverflow($months);
                $m->last_credit_at = null;
            }
            $m->plan_id = $plan->id;
            $m->status = 'active';
            $m->cancelled_at = null;
            $m->reminded_at = null;
            $m->note = $note ?? $m->note;
            $m->addRef($ref + ['planId' => $plan->id, 'periods' => $periods]);
            $m->save();

            return $m->refresh();
        });
    }

    /** $immediately: benefits stop now; otherwise they run to renews_at and no reminder is sent. */
    public function cancel(Membership $m, bool $immediately = false, ?string $note = null): Membership
    {
        $m->forceFill(['status' => $immediately ? 'expired' : 'cancelled', 'cancelled_at' => now(),
            'renews_at' => $immediately ? now() : $m->renews_at, 'note' => $note ?? $m->note])->save();

        return $m;
    }

    /** Daily: wallet credit once per month of membership. Returns members credited. */
    public function creditMonthly(): int
    {
        if (! $this->enabled() || ! Features::enabled('wallet')) {
            return 0;
        }
        $n = 0;
        $days = MoneySettings::int('membership.credit_expiry_days');
        Membership::query()->with(['plan', 'user'])->whereIn('status', ['active', 'cancelled'])->where('renews_at', '>', now())->get()
            ->each(function (Membership $m) use (&$n, $days) {
                $amount = self::normalize($m->plan?->benefits ?? [])['monthly_wallet_credit_bdt'];
                if ($amount <= 0 || ! $m->user || ($m->last_credit_at && $m->last_credit_at->copy()->addMonthNoOverflow()->isFuture())) {
                    return;
                }
                DB::transaction(function () use ($m, $amount, $days) {
                    $this->wallet->credit($m->user, $amount, 'promo', 'Skydrop Plus monthly credit', null, $days > 0 ? now()->addDays($days) : null,
                        ['membership_id' => $m->id]);
                    $m->forceFill(['last_credit_at' => now()])->save();
                });
                $n++;
            });

        return $n;
    }

    /** Daily: expire lapsed memberships and remind members whose plan renews soon (manual renewal). */
    public function remindRenewals(): int
    {
        Membership::query()->whereIn('status', ['active', 'cancelled'])->where('renews_at', '<=', now())->update(['status' => 'expired']);
        if (! $this->enabled()) {
            return 0;
        }
        $days = max(1, MoneySettings::int('membership.reminder_days'));
        $n = 0;
        Membership::query()->with(['plan', 'user'])->where('status', 'active')->whereNull('cancelled_at')
            ->whereBetween('renews_at', [now(), now()->addDays($days)])->whereNull('reminded_at')->get()
            ->each(function (Membership $m) use (&$n) {
                if (! $m->user || ! $m->plan) {
                    return;
                }
                $link = Messenger::webUrl('/plus?renew='.$m->plan->slug);
                app(Notifier::class)->custom($m->user, "Your Skydrop Plus ({$m->plan->name}) ends on ".$m->renews_at->format('j M Y')
                    .'. Renew for ৳'.number_format($m->plan->price_bdt).' to keep your benefits: '.$link, 'sms');
                $m->forceFill(['reminded_at' => now()])->save();
                $n++;
            });

        return $n;
    }

    /** Savings tracker: what Plus saved this member (order discounts + monthly credits). */
    public function savings(User $user): array
    {
        $orders = Order::query()->where('user_id', $user->id)->where('status', '!=', 'cancelled')->where('membership_discount_bdt', '>', 0);
        $credits = (float) \App\Models\WalletLedger::query()->where('user_id', $user->id)->where('type', 'credit')
            ->where('reason', 'promo')->where('note', 'Skydrop Plus monthly credit')->sum('amount_bdt');
        $paid = (float) Order::query()->where('user_id', $user->id)->where('kind', 'membership')->whereIn('payment_status', ['paid'])->sum('total_bdt');
        $orderSaved = (float) (clone $orders)->sum('membership_discount_bdt');

        return [
            'totalBdt' => (int) round($orderSaved + $credits), 'ordersBdt' => (int) round($orderSaved), 'creditsBdt' => (int) round($credits),
            'orders' => (clone $orders)->count(), 'paidBdt' => (int) round($paid),
        ];
    }

    public function membershipApi(?Membership $m): ?array
    {
        if (! $m) {
            return null;
        }

        return [
            'id' => $m->id, 'status' => $m->status, 'plan' => $m->plan?->toApi(), 'startedAt' => $m->started_at?->toIso8601String(),
            'renewsAt' => $m->renews_at?->toIso8601String(), 'cancelledAt' => $m->cancelled_at?->toIso8601String(),
            'daysLeft' => $m->renews_at ? max(0, (int) ceil(now()->diffInDays($m->renews_at, false))) : 0,
        ];
    }
}
