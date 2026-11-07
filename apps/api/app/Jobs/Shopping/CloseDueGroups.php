<?php

namespace App\Jobs\Shopping;

use App\Models\GroupBuy;
use App\Services\Shopping\GroupBuyService;
use App\Support\Features;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Every 5 minutes: closes group buys past their deadline and places the member orders. */
class CloseDueGroups implements ShouldQueue
{
    use Queueable;

    public function handle(GroupBuyService $groups): void
    {
        if (! Features::enabled('group_orders')) {
            return;
        }
        GroupBuy::query()->where('status', 'open')->where('closes_at', '<=', now())->orderBy('closes_at')->limit(50)->get()
            ->each(fn (GroupBuy $g) => rescue(fn () => $groups->close($g, 'Deadline reached'), null, true));
    }
}
