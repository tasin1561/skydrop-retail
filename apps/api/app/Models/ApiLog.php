<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $table = 'api_logs';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'is_retry' => 'boolean'];
    }
}
