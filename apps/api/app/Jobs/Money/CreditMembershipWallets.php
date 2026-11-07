<?php

namespace App\Jobs\Money;

use App\Services\Money\MembershipService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Daily: Skydrop Plus monthly wallet credit (once per member per month; skipped when membership or wallet is off). */
class CreditMembershipWallets implements ShouldQueue
{
    use Queueable;

    public function handle(MembershipService $plus): void
    {
        $plus->creditMonthly();
    }
}
