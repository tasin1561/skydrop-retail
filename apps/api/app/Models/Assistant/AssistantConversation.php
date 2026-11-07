<?php

namespace App\Models\Assistant;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantConversation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['cost_usd' => 'float', 'last_message_at' => 'datetime', 'message_count' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'conversation_id')->orderBy('id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(AssistantFeedback::class, 'conversation_id');
    }
}
