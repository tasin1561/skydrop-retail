<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\PointsLedger;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty points. Ledger rows are signed; positive "lots" (earn/signup/adjust+/refund) expire FIFO after
 * `expire_months`. users.points_balance caches the spendable balance.
 *  - earn: 1 point per ৳earn_per_bdt of item subtotal after coupon/points discounts, credited on delivery
 *  - redeem: blocks of `redeem_block` points = ৳redeem_block_bdt off items, max redeem_max_ratio of item subtotal
 */
class PointsService
{
    public function rules(): array
    {
        $over = rescue(fn () => Setting::get('points', []), [], false);

        return array_merge(config('retail.points'), is_array($over) ? $over : []);
    }

    public function balance(User $user): int
    {
        return (int) PointsLedger::query()->where('user_id', $user->id)->sum('points');
    }

    /** Points that will be earned for an items subtotal (after item discounts). */
    public function toEarn(float $itemsNetBdt): int
    {
        $r = $this->rules();

        return $r['enabled'] ? (int) floor(max(0, $itemsNetBdt) / max(1, $r['earn_per_bdt'])) : 0;
    }

    /**
     * How many of the requested points can be used on this subtotal.
     *
     * @return array{points: int, discount: int, maxPoints: int}
     */
    public function plan(?User $user, int $requested, float $itemSubtotalBdt): array
    {
        $r = $this->rules();
        $block = max(1, (int) $r['redeem_block']);
        $cap = (int) floor($itemSubtotalBdt * $r['redeem_max_ratio'] / $r['redeem_block_bdt']) * $block;
        $have = $user ? (int) User::query()->whereKey($user->id)->value('points_balance') : 0;
        $max = (int) (floor(min($cap, $have) / $block) * $block);
        if (! $r['enabled'] || $requested <= 0) {
            return ['points' => 0, 'discount' => 0, 'maxPoints' => $r['enabled'] ? $max : 0];
        }
        $use = (int) (floor(min($requested, $max) / $block) * $block);

        return ['points' => $use, 'discount' => (int) ($use / $block * $r['redeem_block_bdt']), 'maxPoints' => $max];
    }

    public function signupBonus(User $user): void
    {
        $bonus = (int) ($this->rules()['signup_bonus'] ?? 0);
        if ($bonus > 0 && ! PointsLedger::query()->where('user_id', $user->id)->where('type', 'signup')->exists()) {
            $this->credit($user, $bonus, 'signup', 'Welcome bonus');
        }
    }

    /** Called inside the order transaction. */
    public function redeem(User $user, Order $order, int $points): void
    {
        if ($points <= 0) {
            return;
        }
        DB::transaction(function () use ($user, $order, $points) {
            $fresh = User::query()->lockForUpdate()->find($user->id);
            if ($this->balance($fresh) < $points) {
                throw CommerceException::field('redeemPoints', 'You do not have enough points.');
            }
            $this->consumeLots($user->id, $points);
            PointsLedger::query()->create(['user_id' => $user->id, 'order_id' => $order->id, 'type' => 'redeem', 'points' => -$points, 'note' => "Redeemed on {$order->id}"]);
            $this->sync($user);
        });
    }

    /** Give back points redeemed on a cancelled order (once). */
    public function refund(Order $order): void
    {
        if ($order->points_redeemed <= 0 || PointsLedger::query()->where('order_id', $order->id)->where('type', 'refund')->exists()) {
            return;
        }
        $this->credit($order->user, $order->points_redeemed, 'refund', "Refund for cancelled {$order->id}", $order->id);
    }

    /** Credit points for a delivered order (once). */
    public function earn(Order $order): int
    {
        if ($order->points_to_earn <= 0 || PointsLedger::query()->where('order_id', $order->id)->where('type', 'earn')->exists()) {
            return 0;
        }
        $this->credit($order->user, $order->points_to_earn, 'earn', "Earned on {$order->id}", $order->id);
        $order->forceFill(['points_earned' => $order->points_to_earn])->save();

        return $order->points_to_earn;
    }

    /** Admin adjustment (positive or negative). */
    public function adjust(User $user, int $points, string $note): void
    {
        if ($points === 0) {
            return;
        }
        if ($points > 0) {
            $this->credit($user, $points, 'adjust', $note);

            return;
        }
        DB::transaction(function () use ($user, $points, $note) {
            $take = min(-$points, max(0, $this->balance($user)));
            if ($take > 0) {
                $this->consumeLots($user->id, $take);
                PointsLedger::query()->create(['user_id' => $user->id, 'type' => 'adjust', 'points' => -$take, 'note' => $note]);
            }
            $this->sync($user);
        });
    }

    /** Expire lots past expires_at (FIFO consumption decides what is left). Returns points expired. */
    public function expire(): int
    {
        $total = 0;
        PointsLedger::query()->where('points', '>', 0)->whereNull('settled_at')->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())->orderBy('id')->get()
            ->each(function (PointsLedger $lot) use (&$total) {
                DB::transaction(function () use ($lot, &$total) {
                    $left = $this->remaining($lot);
                    if ($left > 0) {
                        PointsLedger::query()->create(['user_id' => $lot->user_id, 'type' => 'expire', 'points' => -$left, 'note' => 'Expired points from '.$lot->created_at?->format('j M Y')]);
                        $total += $left;
                    }
                    $lot->forceFill(['settled_at' => now()])->save();
                    $this->sync(User::query()->find($lot->user_id));
                });
            });

        return $total;
    }

    /** @return array{balance: int, pending: int, valueBdt: int, history: array} */
    public function summary(User $user): array
    {
        $r = $this->rules();
        $balance = (int) User::query()->whereKey($user->id)->value('points_balance');
        $pending = (int) Order::query()->where('user_id', $user->id)->whereNotIn('status', ['delivered', 'cancelled'])->sum('points_to_earn');
        $history = PointsLedger::query()->where('user_id', $user->id)->latest('id')->limit(100)->get()
            ->map(fn (PointsLedger $p) => ['type' => $p->type === 'signup' ? 'earn' : $p->type, 'points' => $p->points, 'note' => $p->note,
                'at' => $p->created_at?->toIso8601String(), 'orderId' => $p->order_id, 'expiresAt' => $p->expires_at?->toIso8601String()])->all();

        return [
            'balance' => $balance, 'pending' => $pending,
            'valueBdt' => (int) (floor($balance / max(1, $r['redeem_block'])) * $r['redeem_block_bdt']),
            'rules' => ['earnPerBdt' => $r['earn_per_bdt'], 'redeemBlock' => $r['redeem_block'], 'redeemBlockBdt' => $r['redeem_block_bdt'],
                'maxRedeemRatio' => $r['redeem_max_ratio'], 'expireMonths' => $r['expire_months']],
            'history' => $history,
        ];
    }

    private function credit(User $user, int $points, string $type, string $note, ?string $orderId = null): void
    {
        DB::transaction(function () use ($user, $points, $type, $note, $orderId) {
            PointsLedger::query()->create([
                'user_id' => $user->id, 'order_id' => $orderId, 'type' => $type, 'points' => $points, 'note' => $note,
                'expires_at' => now()->addMonths((int) $this->rules()['expire_months']),
            ]);
            $this->sync($user);
        });
    }

    /** Points still unspent in a positive lot: lot minus what FIFO consumption has taken from it. */
    private function remaining(PointsLedger $lot): int
    {
        return max(0, $lot->points - $this->consumedFrom($lot));
    }

    /**
     * FIFO: total spend (redeem/expire/negative adjust) is applied to positive lots oldest first.
     */
    private function consumedFrom(PointsLedger $lot): int
    {
        $spent = -(int) PointsLedger::query()->where('user_id', $lot->user_id)->where('points', '<', 0)->sum('points');
        $before = (int) PointsLedger::query()->where('user_id', $lot->user_id)->where('points', '>', 0)->where('id', '<', $lot->id)->sum('points');

        return (int) min($lot->points, max(0, $spent - $before));
    }

    /** Marks fully used lots as settled so the expiry job skips them. */
    private function consumeLots(int $userId, int $points): void
    {
        // Spending is recorded by the caller's negative row; here we settle lots that become fully used.
        $spent = -(int) PointsLedger::query()->where('user_id', $userId)->where('points', '<', 0)->sum('points') + $points;
        $running = 0;
        PointsLedger::query()->where('user_id', $userId)->where('points', '>', 0)->orderBy('id')->get()
            ->each(function (PointsLedger $lot) use (&$running, $spent) {
                $running += $lot->points;
                if ($running <= $spent && ! $lot->settled_at) {
                    $lot->forceFill(['settled_at' => now()])->save();
                }
            });
    }

    private function sync(?User $user): void
    {
        if ($user) {
            $user->forceFill(['points_balance' => max(0, $this->balance($user))])->saveQuietly();
        }
    }
}
