<?php

namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['cards' => 'array', 'tools' => 'array', 'cost_usd' => 'float', 'degraded' => 'boolean', 'stopped' => 'boolean'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(AssistantFeedback::class, 'message_id');
    }

    /** Shopper-facing shape for GET /assistant/conversations/{id}. */
    public function toApi(): array
    {
        return [
            'id' => $this->id, 'role' => $this->role, 'text' => $this->content, 'events' => $this->cards ?? [],
            'degraded' => $this->degraded, 'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
