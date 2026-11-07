<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** In-app notification centre item. */
class UserNotification extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id, 'type' => $this->type, 'title' => $this->title, 'body' => $this->body, 'icon' => $this->icon,
            'link' => $this->link, 'image' => $this->image, 'data' => $this->data ?? (object) [],
            'read' => $this->read_at !== null, 'readAt' => $this->read_at?->toIso8601String(), 'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
