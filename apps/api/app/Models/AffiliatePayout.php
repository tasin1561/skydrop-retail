<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePayout extends Model
{
    public const STATUSES = ['requested' => 'Requested', 'paid' => 'Paid', 'rejected' => 'Rejected'];

    public const COLORS = ['requested' => 'warning', 'paid' => 'success', 'rejected' => 'gray'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_bdt' => 'float', 'paid_at' => 'datetime'];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'payout_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id, 'amountBdt' => (int) round($this->amount_bdt), 'method' => $this->method, 'status' => $this->status,
            'reference' => $this->reference, 'requestedAt' => $this->created_at?->toIso8601String(), 'paidAt' => $this->paid_at?->toIso8601String(),
        ];
    }
}
