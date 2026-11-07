<?php

namespace App\Models\Fulfilment;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    public const REASONS = [
        'damaged' => 'Damaged', 'wrong_item' => 'Wrong item', 'not_as_described' => 'Not as described',
        'size_issue' => 'Size issue', 'changed_mind' => 'Changed my mind',
    ];

    public const RESOLUTIONS = ['refund' => 'Refund', 'replacement' => 'Replacement', 'store_credit' => 'Store credit'];

    /** requested → approved/rejected → pickup_scheduled → received → refunded/replaced/credited */
    public const STATUSES = [
        'requested' => 'Requested', 'approved' => 'Approved', 'rejected' => 'Rejected', 'pickup_scheduled' => 'Pickup scheduled',
        'received' => 'Received', 'refunded' => 'Refunded', 'replaced' => 'Replaced', 'credited' => 'Store credit issued',
    ];

    public const FINAL = ['rejected', 'refunded', 'replaced', 'credited'];

    protected $table = 'return_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array', 'photos' => 'array', 'timeline' => 'array', 'refund_bdt' => 'float',
            'pickup_at' => 'datetime', 'received_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::FINAL, true);
    }

    public function toApi(): array
    {
        $uploads = Upload::query()->whereIn('id', (array) $this->photos)->get();

        return [
            'id' => $this->id, 'orderId' => $this->order_id, 'stage' => $this->stage, 'items' => $this->items,
            'reason' => $this->reason, 'reasonLabel' => self::REASONS[$this->reason] ?? $this->reason, 'note' => $this->note,
            'photos' => $uploads->map(fn (Upload $u) => $u->signedUrl())->values()->all(),
            'resolution' => $this->resolution, 'status' => $this->status, 'statusLabel' => self::STATUSES[$this->status] ?? $this->status,
            'refundBdt' => (int) round($this->refund_bdt), 'replacementOrderId' => $this->replacement_order_id,
            'pickupAt' => $this->pickup_at?->toIso8601String(), 'createdAt' => $this->created_at?->toIso8601String(),
            'timeline' => array_values(array_map(fn ($e) => ['status' => $e['status'], 'at' => $e['at'], 'note' => $e['note'] ?? null], (array) $this->timeline)),
        ];
    }
}
