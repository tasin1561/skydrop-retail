<?php

namespace App\Jobs\Messaging;

use App\Models\PointsLedger;
use App\Models\User;
use App\Services\Commerce\Notifier;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Daily: shoppers with points lots expiring within 14 days (capped at their balance) get one reminder per expiry date. */
class SendPointsExpiryReminders implements ShouldQueue
{
    use Queueable;

    public function handle(Notifier $notifier): void
    {
        $days = (int) MessagingSettings::get('reminders.points_expiring_days', 14);
        PointsLedger::query()->where('points', '>', 0)->whereNull('settled_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->get()->groupBy('user_id')
            ->each(function ($lots, $userId) use ($notifier) {
                $user = User::query()->find($userId);
                if (! $user || $user->blocked_at) {
                    return;
                }
                $points = min((int) $lots->sum('points'), (int) $user->points_balance);
                if ($points <= 0) {
                    return;
                }
                $first = Carbon::parse($lots->min('expires_at'));
                if (Cache::add('msg:points_expiring:'.$userId.':'.$first->toDateString(), 1, now()->addDays($days + 1))) {
                    $notifier->pointsExpiring($user, $points, $first);
                }
            });
    }
}
