<?php

namespace App\Services\Money;

use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\PointsService;
use App\Support\Features;

/**
 * Money-module step at the end of CheckoutService::quote():
 *  1. Skydrop Plus benefits (MembershipService::benefitsFor) → `membership` lines, inside `discount` and `total`.
 *     Non-members get `plusPreview` ("You'd save ৳X with Plus").
 *  2. Wallet (opts.useWallet: true = as much as possible, or a BDT cap) → `walletApplied`; `payNow` = total − wallet.
 *  3. Card EMI (opts.payment = card_emi, emiTenure, emiBank) on the amount left after the wallet → `emiCharge` added to `total`.
 */
class MoneyQuote
{
    public function __construct(private MembershipService $membership, private WalletService $wallet, private EmiService $emi, private PointsService $points) {}

    public function apply(array $q, ?User $user, array $opts = []): array
    {
        $itemsNet = max(0, (int) $q['subtotal'] - (int) $q['itemDiscount'] - (int) ($q['bundleDiscount'] ?? 0) - (int) $q['pointsDiscount']);
        $shipNet = max(0, (int) $q['shipping'] - (int) $q['shipDiscount']);

        // 1. Skydrop Plus
        $q['membership'] = null;
        $q['membershipDiscount'] = 0;
        $q['plusPreview'] = null;
        $benefits = $this->membership->benefitsFor($user);
        if ($benefits) {
            $m = $this->membership->apply($benefits, $q['groups'], $itemsNet, $shipNet);
            $q['membershipDiscount'] = $m['total'];
            $q['discount'] += $m['total'];
            $q['total'] = max(0, (int) $q['total'] - $m['total']);
            $earn = $this->points->toEarn($itemsNet - $m['items']);
            $q['pointsToEarn'] = (int) floor($earn * $benefits['points_multiplier']);
            $q['membership'] = ['active' => true, 'plan' => $benefits['plan'], 'lines' => $m['lines'], 'savedBdt' => $m['total'],
                'pointsMultiplier' => $benefits['points_multiplier'], 'bonusPoints' => $q['pointsToEarn'] - $earn];
        } elseif ($this->membership->enabled() && ($plan = $this->membership->nudgePlan())) {
            $b = MembershipService::normalize($plan->benefits ?? []);
            $m = $this->membership->apply($b, $q['groups'], $itemsNet, $shipNet);
            if ($m['total'] > 0) {
                $q['plusPreview'] = ['plan' => ['name' => $plan->name, 'slug' => $plan->slug, 'priceBdt' => (int) round($plan->price_bdt), 'period' => $plan->period],
                    'savedBdt' => $m['total'], 'lines' => $m['lines']];
            }
        }

        // 2. Wallet
        $use = $opts['useWallet'] ?? false;
        $q['wallet'] = null;
        $q['walletApplied'] = 0;
        if ($user && Features::enabled('wallet')) {
            $balance = (int) floor($this->wallet->balance($user));
            $applied = 0;
            if ($use !== false && $use !== null && $use !== 0 && $use !== '0') {
                $cap = is_numeric($use) && ! is_bool($use) ? max(0, (int) $use) : PHP_INT_MAX;
                $applied = (int) min($balance, $cap, (int) $q['total']);
            }
            $q['wallet'] = ['balanceBdt' => $balance, 'appliedBdt' => $applied];
            $q['walletApplied'] = $applied;
        }

        // 3. Card EMI on what is left to pay by card
        $q['emi'] = null;
        $q['emiCharge'] = 0;
        $financed = (int) $q['total'] - $q['walletApplied'];
        if (($opts['payment'] ?? null) === 'card_emi') {
            $plan = $this->emi->plan($financed, (int) ($opts['emiTenure'] ?? 0), $opts['emiBank'] ?? null);
            if (! $plan['eligible'] && ! empty($opts['strict'])) {
                throw CommerceException::field('emiTenure', (string) $plan['reason']);
            }
            $q['emi'] = $plan;
            if ($plan['eligible']) {
                $q['emiCharge'] = $plan['chargeBdt'];
                $q['total'] = (int) $q['total'] + $plan['chargeBdt'];
            }
        }
        $q['emiAvailable'] = $this->emi->eligible($financed);
        $q['payNow'] = max(0, (int) $q['total'] - $q['walletApplied']);

        return $q;
    }
}
