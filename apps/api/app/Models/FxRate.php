<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxRate extends Model
{
    protected $table = 'fx_rates';

    public $timestamps = false; // the table only has fetched_at

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rate' => 'float', 'fetched_at' => 'datetime'];
    }
}
