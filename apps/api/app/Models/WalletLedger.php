<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Store-credit wallet ledger row. amount_bdt is positive; `type` (credit|debit) gives the sign. */
class WalletLedger extends Model
{
    protected $table = 'wallet_ledger';

    protected $guarded = [];

    public const REASONS = ['refund' => 'Refund', 'gift_card' => 'Gift card', 'admin' => 'Admin adjustment', 'promo' => 'Promotion',
        'order_payment' => 'Order payment', 'expire' => 'Expired'];

    protected function casts(): array
    {
        return ['amount_bdt' => 'float', 'remaining_bdt' => 'float', 'balance_after_bdt' => 'float', 'expires_at' => 'datetime', 'meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signed(): float
    {
        return $this->type === 'credit' ? $this->amount_bdt : -$this->amount_bdt;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id, 'type' => $this->type, 'amountBdt' => (int) round($this->amount_bdt), 'reason' => $this->reason,
            'note' => $this->note, 'orderId' => $this->order_id, 'balanceAfterBdt' => (int) round($this->balance_after_bdt),
            'expiresAt' => $this->expires_at?->toIso8601String(), 'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
