<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCard extends Model
{
    protected $guarded = [];

    public const STATUSES = ['pending' => 'Awaiting payment', 'active' => 'Active', 'redeemed' => 'Redeemed', 'void' => 'Void', 'expired' => 'Expired'];

    protected function casts(): array
    {
        return ['initial_bdt' => 'float', 'balance_bdt' => 'float', 'expires_at' => 'datetime', 'delivered_at' => 'datetime', 'redeemed_at' => 'datetime'];
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_user_id');
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by_user_id');
    }

    /** XXXX-XXXX-XXXX-XXXX */
    public static function format(string $code): string
    {
        return implode('-', str_split(strtoupper($code), 4));
    }

    /** Strips spaces/dashes and upper-cases a typed code. */
    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function formatted(): string
    {
        return self::format($this->code);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** $full: include the code (purchaser only). */
    public function toApi(bool $full = false): array
    {
        return array_filter([
            'id' => $this->id, 'code' => $full && $this->status !== 'pending' ? $this->formatted() : null,
            'last4' => substr($this->code, -4), 'initialBdt' => (int) round($this->initial_bdt), 'balanceBdt' => (int) round($this->balance_bdt),
            'design' => $this->design, 'status' => $this->status, 'recipientName' => $this->recipient_name, 'recipientPhone' => $this->recipient_phone,
            'recipientEmail' => $this->recipient_email, 'message' => $this->message, 'orderId' => $this->order_id,
            'expiresAt' => $this->expires_at?->toIso8601String(), 'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'redeemedAt' => $this->redeemed_at?->toIso8601String(), 'createdAt' => $this->created_at?->toIso8601String(),
        ], fn ($v) => $v !== null);
    }
}
