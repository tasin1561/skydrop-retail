<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marketplace extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'live_fetch' => 'boolean', 'settings' => 'array'];
    }

    public static function enabledIds(): array
    {
        return static::query()->where('enabled', true)->pluck('id')->all();
    }
}
