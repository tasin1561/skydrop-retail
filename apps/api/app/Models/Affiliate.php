<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'suspended' => 'Suspended'];

    public const PAYOUT_METHODS = ['bkash' => 'bKash', 'nagad' => 'Nagad'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['default_rate' => 'float', 'approved_at' => 'datetime', 'code_changed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
