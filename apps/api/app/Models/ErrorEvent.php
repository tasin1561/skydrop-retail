<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per exception fingerprint (class + file + line), filled by App\Services\System\ErrorRecorder. */
class ErrorEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'count' => 'integer', 'line' => 'integer'];
    }
}
