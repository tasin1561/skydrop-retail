<?php

namespace App\Models\Fulfilment;

use Illuminate\Database\Eloquent\Model;

/** Raw Skydrop webhook events per order (scans, rider pings with lat/lng, proof of delivery). */
class SkydropEvent extends Model
{
    public $timestamps = false;

    protected $table = 'skydrop_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'lat' => 'float', 'lng' => 'float', 'at' => 'datetime'];
    }
}
