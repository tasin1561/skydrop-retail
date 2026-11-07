<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cached courier delivery history for one phone (hashed) and one courier. */
class CourierCheck extends Model
{
    protected $table = 'courier_checks';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['raw', 'phone_hash'];

    protected function casts(): array
    {
        return ['raw' => 'encrypted:array', 'checked_at' => 'datetime', 'total' => 'integer', 'delivered' => 'integer', 'cancelled' => 'integer'];
    }
}
