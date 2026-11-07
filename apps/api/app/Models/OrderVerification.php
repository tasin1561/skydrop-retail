<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderVerification extends Model
{
    public $timestamps = false;

    protected $table = 'order_verifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['raw' => 'array', 'ok' => 'boolean', 'deliverable' => 'boolean', 'verified_at' => 'datetime'];
    }
}
