<?php

namespace App\Services\Assistant;

use App\Models\Assistant\AssistantMessage;
use App\Models\User;
use App\Support\Limits;
use Throwable;

/** Daily message limits: guests per IP, signed-in shoppers per account, more for Skydrop Plus members. */
class AssistantLimits
{
    private const MEMBERSHIP = [
        'App\\Services\\Money\\MembershipService',
        'App\\Services\\Membership\\MembershipService',
        'App\\Services\\MembershipService',
        'App\\Services\\Commerce\\MembershipService',
        'App\\Services\\Growth\\MembershipService',
    ];

    public function limit(AssistantContext $ctx): int
    {
        // System → Limits (backed by the same "assistant.limits.*" settings as Admin → AI assistant).
        if ($ctx->guest()) {
            return Limits::int('assistant.guest_per_day');
        }

        return Limits::int($this->isPlus($ctx->user) ? 'assistant.plus_per_day' : 'assistant.user_per_day');
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
                if (method_exists($svc, 'enabled') && ! $svc->enabled()) {
                    return false;
                }
                if (method_exists($svc, 'current')) {
                    return $svc->current($user) !== null; // Skydrop Plus: an active membership
                }
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
