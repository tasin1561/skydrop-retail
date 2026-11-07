<?php

namespace App\Services\Growth;

use App\Models\Order;
use App\Models\ReferralReward;
use App\Models\User;
use App\Services\Commerce\Phone;
use App\Services\Commerce\PointsService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Refer a friend: every shopper has users.referral_code. A new account created with ?invite=CODE (sent at OTP verify)
 * stores referred_by. When that friend's first order is delivered both get points (referral.inviter_points /
 * referral.friend_points) through PointsService, once per friend (referral_rewards.friend_id is unique).
 */
class ReferralService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I

    public function __construct(private PointsService $points) {}

    public static function normalizeCode(?string $code): ?string
    {
        $c = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z0-9]{4,12}$/', $c) ? $c : null;
    }

    public function codeFor(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }
        for ($i = 0; $i < 5; $i++) {
            $code = $this->randomCode(7);
            try {
                $updated = User::query()->whereKey($user->id)->whereNull('referral_code')->update(['referral_code' => $code]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
            $user->referral_code = $updated ? $code : User::query()->whereKey($user->id)->value('referral_code');

            return $user->referral_code;
        }
        throw new \RuntimeException('Could not allocate a referral code.');
    }

    public function randomCode(int $len): string
    {
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $out;
    }

    /** Links a brand-new account to its inviter. Returns false for unknown codes and self-invites. */
    public function attachInvite(User $friend, ?string $code): bool
    {
        $c = self::normalizeCode($code);
        if (! $c || $friend->referred_by) {
            return false;
        }
        $inviter = User::query()->where('referral_code', $c)->first();
        if (! $inviter || $inviter->id === $friend->id || $inviter->blocked_at
            || (Phone::normalize($inviter->phone) && Phone::normalize($inviter->phone) === Phone::normalize($friend->phone))) {
            return false;
        }
        $friend->forceFill(['referred_by' => $inviter->id])->saveQuietly();

        return true;
    }

    /** Called when an order becomes delivered. Rewards both sides on the friend's first delivered order. */
    public function onDelivered(Order $order): ?ReferralReward
    {
        $friend = $order->user;
        if (! $friend?->referred_by || ReferralReward::query()->where('friend_id', $friend->id)->exists()) {
            return null;
        }
        $inviter = User::query()->find($friend->referred_by);
        if (! $inviter || $inviter->blocked_at) {
            return null;
        }
        // A shopper ordering to the inviter's own phone is not a new customer.
        if (Phone::normalize($inviter->phone) && Phone::normalize($inviter->phone) === Phone::normalize($order->address['phone'] ?? null)) {
            return null;
        }
        $ip = max(0, GrowthSettings::int('referral.inviter_points'));
        $fp = max(0, GrowthSettings::int('referral.friend_points'));

        try {
            return DB::transaction(function () use ($inviter, $friend, $order, $ip, $fp) {
                $reward = ReferralReward::query()->create([
                    'inviter_id' => $inviter->id, 'friend_id' => $friend->id, 'order_id' => $order->id,
                    'inviter_points' => $ip, 'friend_points' => $fp,
                ]);
                $first = explode(' ', trim($friend->name))[0] ?: 'your friend';
                $this->points->adjust($inviter, $ip, "Referral bonus: {$first}'s first order {$order->id} was delivered");
                $this->points->adjust($friend, $fp, "Referral welcome bonus: your first order {$order->id} was delivered");

                return $reward;
            });
        } catch (UniqueConstraintViolationException) {
            return null; // rewarded concurrently
        }
    }

    public function link(User $user): string
    {
        return rtrim((string) config('retail.web_url'), '/').'/?invite='.$this->codeFor($user);
    }

    /** GET /referrals/me */
    public function summary(User $user): array
    {
        $code = $this->codeFor($user);
        $friends = User::query()->where('referred_by', $user->id)->latest('id')->limit(50)->get(['id', 'name', 'created_at']);
        $rewards = ReferralReward::query()->where('inviter_id', $user->id)->get()->keyBy('friend_id');
        $welcome = ReferralReward::query()->where('friend_id', $user->id)->first();

        return [
            'code' => $code,
            'link' => $this->link($user),
            'invited' => User::query()->where('referred_by', $user->id)->count(),
            'rewarded' => $rewards->count(),
            'pointsEarned' => (int) $rewards->sum('inviter_points') + (int) ($welcome?->friend_points ?? 0),
            'rules' => ['inviterPoints' => GrowthSettings::int('referral.inviter_points'), 'friendPoints' => GrowthSettings::int('referral.friend_points')],
            'friends' => $friends->map(fn (User $f) => [
                'name' => self::shortName($f->name), 'joinedAt' => $f->created_at?->toIso8601String(),
                'status' => isset($rewards[$f->id]) ? 'rewarded' : 'joined',
            ])->all(),
        ];
    }

    /** "Karim Uddin" -> "Karim U." */
    public static function shortName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $first = $parts[0] ?? 'Friend';

        return isset($parts[1]) && $parts[1] !== '' ? $first.' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.' : $first;
    }
}
