<?php

namespace App\Jobs\Money;

use App\Models\GiftCard;
use App\Services\Money\MembershipService;
use App\Services\Money\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Daily: expire wallet credit lots and gift cards, expire lapsed memberships, send Plus renewal reminders (manual renewal). */
class MoneyHousekeeping implements ShouldQueue
{
    use Queueable;

    public function handle(WalletService $wallet, MembershipService $plus): void
    {
        $wallet->expire();
        GiftCard::query()->where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now())->update(['status' => 'expired']);
        $plus->remindRenewals();
    }
}
