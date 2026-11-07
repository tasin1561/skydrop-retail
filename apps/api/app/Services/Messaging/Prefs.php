<?php

namespace App\Services\Messaging;

use App\Models\User;

/** Shopper notification preferences (users.notify_prefs). Transactional messages ignore these. */
final class Prefs
{
    public const CHANNELS = ['sms', 'email', 'push', 'inapp'];

    public const DEFAULTS = ['marketing' => ['sms' => true, 'email' => true, 'push' => true, 'inapp' => true]];

    /** @return array{marketing: array{sms: bool, email: bool, push: bool, inapp: bool}} */
    public static function of(User $user): array
    {
        $p = is_array($user->notify_prefs) ? $user->notify_prefs : [];
        $m = [];
        foreach (self::CHANNELS as $c) {
            $m[$c] = (bool) ($p['marketing'][$c] ?? self::DEFAULTS['marketing'][$c]);
        }

        return ['marketing' => $m];
    }

    public static function allowsMarketing(User $user, string $channel): bool
    {
        return self::of($user)['marketing'][$channel] ?? false;
    }

    public static function set(User $user, array $marketing): void
    {
        $cur = self::of($user);
        foreach (self::CHANNELS as $c) {
            if (array_key_exists($c, $marketing)) {
                $cur['marketing'][$c] = (bool) $marketing[$c];
            }
        }
        $user->forceFill(['notify_prefs' => $cur])->save();
    }

    public static function locale(?User $user): string
    {
        return in_array($user?->locale, ['en', 'bn'], true) ? $user->locale : 'en';
    }
}
