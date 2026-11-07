<?php

namespace App\Services\Money;

use App\Models\User;
use App\Models\WalletLedger;
use App\Services\Commerce\CommerceException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Skydrop Wallet (store credit, BDT). Every change is a wallet_ledger row written inside a transaction that locks the
 * user row; users.wallet_balance_bdt caches the balance and can never go negative.
 * Credits are "lots" (remaining_bdt) spent FIFO, soonest-expiring first; expire() removes what is left of expired lots.
 */
class WalletService
{
    public function balance(User $user): float
    {
        return (float) User::query()->whereKey($user->id)->value('wallet_balance_bdt');
    }

    /** Sum of the ledger (the cached balance must always equal this). */
    public function ledgerBalance(User $user): float
    {
        $rows = WalletLedger::query()->where('user_id', $user->id)->selectRaw("type, SUM(amount_bdt) as s")->groupBy('type')->pluck('s', 'type');

        return round((float) ($rows['credit'] ?? 0) - (float) ($rows['debit'] ?? 0), 2);
    }

    public function credit(User $user, float $amount, string $reason, ?string $note = null, ?string $orderId = null,
        ?Carbon $expiresAt = null, array $meta = [], ?int $staffId = null): WalletLedger
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw CommerceException::field('amount', 'Amount must be more than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $reason, $note, $orderId, $expiresAt, $meta, $staffId) {
            $u = User::query()->lockForUpdate()->findOrFail($user->id);
            $after = round((float) $u->wallet_balance_bdt + $amount, 2);
            $row = WalletLedger::query()->create([
                'user_id' => $u->id, 'type' => 'credit', 'amount_bdt' => $amount, 'remaining_bdt' => $amount, 'reason' => $reason,
                'order_id' => $orderId, 'expires_at' => $expiresAt, 'balance_after_bdt' => $after, 'note' => $note,
                'meta' => $meta ?: null, 'staff_id' => $staffId,
            ]);
            $u->forceFill(['wallet_balance_bdt' => $after])->saveQuietly();
            $user->wallet_balance_bdt = $after;

            return $row;
        });
    }

    /** Spends store credit. Throws (422, field "wallet") when the balance is not enough: never goes negative. */
    public function debit(User $user, float $amount, string $reason, ?string $note = null, ?string $orderId = null,
        array $meta = [], ?int $staffId = null): WalletLedger
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw CommerceException::field('amount', 'Amount must be more than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $reason, $note, $orderId, $meta, $staffId) {
            $u = User::query()->lockForUpdate()->findOrFail($user->id);
            $have = (float) $u->wallet_balance_bdt;
            if ($have + 0.001 < $amount) {
                throw CommerceException::field('wallet', 'Your Skydrop Wallet balance (৳'.number_format($have).') is not enough.');
            }
            $this->consume($u->id, $amount);
            $after = max(0, round($have - $amount, 2));
            $row = WalletLedger::query()->create([
                'user_id' => $u->id, 'type' => 'debit', 'amount_bdt' => $amount, 'reason' => $reason, 'order_id' => $orderId,
                'balance_after_bdt' => $after, 'note' => $note, 'meta' => $meta ?: null, 'staff_id' => $staffId,
            ]);
            $u->forceFill(['wallet_balance_bdt' => $after])->saveQuietly();
            $user->wallet_balance_bdt = $after;

            return $row;
        });
    }

    /** Removes the unspent part of expired credit lots. Returns BDT expired. */
    public function expire(): float
    {
        $total = 0.0;
        WalletLedger::query()->where('type', 'credit')->where('remaining_bdt', '>', 0)->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())->orderBy('id')->get()
            ->each(function (WalletLedger $lot) use (&$total) {
                DB::transaction(function () use ($lot, &$total) {
                    $u = User::query()->lockForUpdate()->find($lot->user_id);
                    $lot->refresh();
                    $left = min((float) $lot->remaining_bdt, (float) $u->wallet_balance_bdt);
                    $lot->forceFill(['remaining_bdt' => 0])->save();
                    if ($left <= 0) {
                        return;
                    }
                    $after = max(0, round((float) $u->wallet_balance_bdt - $left, 2));
                    WalletLedger::query()->create([
                        'user_id' => $u->id, 'type' => 'debit', 'amount_bdt' => $left, 'reason' => 'expire', 'balance_after_bdt' => $after,
                        'note' => 'Expired credit from '.$lot->created_at?->format('j M Y'), 'meta' => ['lot' => $lot->id],
                    ]);
                    $u->forceFill(['wallet_balance_bdt' => $after])->saveQuietly();
                    $total += $left;
                });
            });

        return $total;
    }

    /** @return array{balanceBdt: int, expiringBdt: int, expiringAt: ?string, history: array} */
    public function summary(User $user): array
    {
        $soon = WalletLedger::query()->where('user_id', $user->id)->where('type', 'credit')->where('remaining_bdt', '>', 0)
            ->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(30))->orderBy('expires_at')->get();

        return [
            'balanceBdt' => (int) floor($this->balance($user)),
            'expiringBdt' => (int) round($soon->sum('remaining_bdt')),
            'expiringAt' => $soon->first()?->expires_at?->toIso8601String(),
            'history' => WalletLedger::query()->where('user_id', $user->id)->latest('id')->limit(100)->get()->map->toApi()->values()->all(),
        ];
    }

    /** FIFO: spends lots that expire soonest first, then non-expiring lots oldest first. */
    private function consume(int $userId, float $amount): void
    {
        $left = $amount;
        $lots = WalletLedger::query()->where('user_id', $userId)->where('type', 'credit')->where('remaining_bdt', '>', 0)
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')->orderBy('expires_at')->orderBy('id')->get();
        foreach ($lots as $lot) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, (float) $lot->remaining_bdt);
            $lot->forceFill(['remaining_bdt' => round($lot->remaining_bdt - $take, 2)])->save();
            $left = round($left - $take, 2);
        }
    }
}
