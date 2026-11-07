<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupBuyMember extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array', 'address' => 'array', 'share_bdt' => 'float'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GroupBuy::class, 'group_buy_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
