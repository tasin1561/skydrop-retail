<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderHealth extends Model
{
    protected $table = 'provider_health';
    protected $primaryKey = 'provider';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime'];
    }
}
