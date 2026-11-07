<?php

namespace App\Services\Messaging;

use App\Models\Order;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Broadcast segments and dry-run estimates. Segment: { type: all|recent_buyers|store|has_points, days?, market?, minPoints? } */
final class BroadcastAudience
{
    public const TYPES = ['all' => 'All customers', 'recent_buyers' => 'Bought in the last N days', 'store' => 'Bought from a store', 'has_points' => 'Has points'];

    public static function query(array $segment): Builder
    {
        $q = User::query()->where(fn ($q) => $q->whereNull('is_admin')->orWhere('is_admin', false))->whereNull('blocked_at');

        return match ($segment['type'] ?? 'all') {
            'recent_buyers' => $q->whereIn('id', Order::query()->select('user_id')->where('status', '!=', 'cancelled')
                ->where('created_at', '>=', now()->subDays(max(1, (int) ($segment['days'] ?? 30))))),
            'store' => $q->whereIn('id', Order::query()->select('user_id')->where('status', '!=', 'cancelled')
                ->whereHas('items', fn ($i) => $i->where('market', $segment['market'] ?? 'amazon'))),
            'has_points' => $q->where('points_balance', '>=', max(1, (int) ($segment['minPoints'] ?? 1))),
            default => $q,
        };
    }

    /**
     * Reachable recipients per channel after opt-outs, and the SMS cost estimate.
     *
     * @return array{users: int, sms: int, email: int, push: int, inapp: int, smsSegments: int, smsCostBdt: float}
     */
    public static function estimate(array $segment, array $channels, string $smsText = ''): array
    {
        $out = ['users' => 0, 'sms' => 0, 'email' => 0, 'push' => 0, 'inapp' => 0];
        $withDevices = PushSubscription::query()->where('kind', 'expo')->distinct()->pluck('user_id')->flip();
        self::query($segment)->select(['id', 'phone', 'email', 'notify_prefs'])->chunkById(500, function ($users) use (&$out, $channels, $withDevices) {
            foreach ($users as $u) {
                $out['users']++;
                $m = Prefs::of($u)['marketing'];
                if (in_array('sms', $channels, true) && $u->phone && $m['sms']) {
                    $out['sms']++;
                }
                if (in_array('email', $channels, true) && $u->email && $m['email']) {
                    $out['email']++;
                }
                if (in_array('push', $channels, true) && $m['push'] && isset($withDevices[$u->id])) {
                    $out['push']++;
                }
                if (in_array('inapp', $channels, true) && $m['inapp']) {
                    $out['inapp']++;
                }
            }
        });
        $seg = $smsText !== '' ? SmsSegments::count($smsText) : 1;
        $out['smsSegments'] = $out['sms'] * $seg;
        $out['smsCostBdt'] = round($out['smsSegments'] * (float) MessagingSettings::get('sms.cost_per_segment_bdt', 0.35), 2);

        return $out;
    }
}
