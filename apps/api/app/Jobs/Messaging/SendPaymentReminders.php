<?php

namespace App\Jobs\Messaging;

use App\Models\Order;
use App\Services\Commerce\Notifier;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Goods orders still unpaid 30 minutes after checkout get one reminder with a retry link. */
class SendPaymentReminders implements ShouldQueue
{
    use Queueable;

    public function handle(Notifier $notifier): void
    {
        $after = (int) MessagingSettings::get('reminders.payment_after_minutes', 30);
        // Goods orders only: an abandoned gift card / Plus checkout is not an order waiting to ship.
        Order::query()->goods()->with(['user', 'items'])
            ->where('status', 'placed')->whereIn('payment_status', ['pending', 'failed'])
            ->whereBetween('created_at', [now()->subDay(), now()->subMinutes($after)])
            ->limit(500)->get()
            ->each(function (Order $o) use ($notifier) {
                if (Cache::add('msg:payment_pending:'.$o->id, 1, now()->addDays(3))) {
                    $notifier->paymentPending($o);
                }
            });
    }
}
