<?php

namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantFeedback extends Model
{
    protected $table = 'assistant_feedback';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class, 'message_id');
    }
}
