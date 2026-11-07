<?php

namespace App\Services\Assistant;

use App\Models\Assistant\AssistantMessage;
use App\Models\User;
use Throwable;

/** Daily message limits: guests per IP, signed-in shoppers per account, more for Skydrop Plus members. */
class AssistantLimits
{
    private const MEMBERSHIP = [
        'App\\Services\\Membership\\MembershipService',
        'App\\Services\\MembershipService',
        'App\\Services\\Commerce\\MembershipService',
        'App\\Services\\Growth\\MembershipService',
    ];

    public function limit(AssistantContext $ctx): int
    {
        if ($ctx->guest()) {
            return (int) AssistantSettings::get('limits.guest', 10);
        }

        return $this->isPlus($ctx->user) ? (int) AssistantSettings::get('limits.plus', 120) : (int) AssistantSettings::get('limits.user', 40);
    }

    public function used(AssistantContext $ctx): int
    {
        $q = AssistantMessage::query()->where('role', 'user')->where('created_at', '>=', now()->startOfDay());

        return $ctx->guest() ? $q->whereNull('user_id')->where('ip_hash', $ctx->ipHash)->count() : $q->where('user_id', $ctx->user->id)->count();
    }

    public function remaining(AssistantContext $ctx): int
    {
        return max(0, $this->limit($ctx) - $this->used($ctx));
    }

    public function isPlus(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        foreach (self::MEMBERSHIP as $class) {
            if (! class_exists($class)) {
                continue;
            }
            try {
                $svc = app($class);
                foreach (['isPlus', 'isMember', 'isActive', 'active', 'hasActiveMembership'] as $m) {
                    if (method_exists($svc, $m)) {
                        return (bool) $svc->{$m}($user);
                    }
                }
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }
}
