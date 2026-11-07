<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Group buy: friends order from one store together; one consolidated shipment, shipping split by weight. */
class GroupBuy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['closes_at' => 'datetime', 'closed_at' => 'datetime', 'shipping_bdt' => 'float', 'min_members' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupBuyMember::class)->orderBy('id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->whereIn('status', ['joined', 'ordered', 'failed']);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'group_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->closes_at->isFuture();
    }
}
