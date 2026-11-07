<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'reversed' => 'Reversed', 'paid' => 'Paid'];

    public const COLORS = ['pending' => 'warning', 'approved' => 'info', 'reversed' => 'danger', 'paid' => 'success'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_bdt' => 'float', 'rate' => 'float', 'amount_bdt' => 'float', 'approve_after' => 'datetime',
            'approved_at' => 'datetime', 'reversed_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'payout_id');
    }

    /** "SR-••••AB": affiliates see that an order happened, not whose it is. */
    public static function maskOrderId(string $id): string
    {
        return substr($id, 0, 3).'••••'.substr($id, -2);
    }
}
