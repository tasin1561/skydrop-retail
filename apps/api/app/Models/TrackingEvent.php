<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Server-side tracking send log (one row per event_id + destination). */
class TrackingEvent extends Model
{
    public const STATUSES = ['queued', 'sent', 'failed', 'skipped'];

    public const DESTINATIONS = ['delivro', 'meta', 'ga4', 'tiktok'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'sent_at' => 'datetime', 'response_code' => 'integer', 'attempts' => 'integer'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
