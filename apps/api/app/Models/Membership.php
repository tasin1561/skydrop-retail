<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    protected $guarded = [];

    public const STATUSES = ['active' => 'Active', 'expired' => 'Expired', 'cancelled' => 'Cancelled'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'renews_at' => 'datetime', 'cancelled_at' => 'datetime', 'last_credit_at' => 'datetime',
            'reminded_at' => 'datetime', 'payment_refs' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    /** Benefits apply while paid through, even after a cancel (no renewal). */
    public function isCurrent(): bool
    {
        return in_array($this->status, ['active', 'cancelled'], true) && $this->renews_at !== null && $this->renews_at->isFuture();
    }

    public function addRef(array $ref): void
    {
        $this->payment_refs = [...($this->payment_refs ?? []), $ref + ['at' => now()->toIso8601String()]];
    }
}
