<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequest extends Model
{
    public const TYPES = ['return', 'damaged', 'wrong_item', 'other'];

    protected $table = 'support_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['photos' => 'array', 'priority' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => (string) $this->id, 'orderId' => $this->order_id, 'type' => $this->type, 'message' => $this->message,
            'status' => $this->status, 'priority' => (bool) $this->priority, 'photos' => $this->photos ?? [], 'adminNote' => $this->admin_note,
            'createdAt' => $this->created_at?->toIso8601String(), 'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
