<?php

namespace App\Models;

use App\Services\Security\Blocklist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Blocked phone, IP or device. `value` is encrypted at rest; lookups use `value_hash` (keyed HMAC). */
class BlockedEntry extends Model
{
    protected $table = 'blocklist';

    protected $guarded = [];

    protected $hidden = ['value_hash', 'value'];

    public const TYPES = ['phone' => 'Phone', 'ip' => 'IP address', 'device' => 'Device id'];

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (BlockedEntry $e) {
            $e->value_hash = Blocklist::hash((string) $e->type, (string) $e->value);
        });
        static::saved(fn () => Blocklist::flush());
        static::deleted(fn () => Blocklist::flush());
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
