<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['keys' => 'array', 'last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Upsert by token (a device that signs in to another account moves to that account). */
    public static function register(int $userId, string $kind, string $token, ?array $keys = null, ?string $platform = null): self
    {
        return static::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $kind.'|'.$token)],
            ['user_id' => $userId, 'kind' => $kind, 'token' => $token, 'keys' => $keys, 'platform' => $platform, 'last_used_at' => now()],
        );
    }
}
