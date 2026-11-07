<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    public $timestamps = false;

    protected $table = 'order_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }
}
