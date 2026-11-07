<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Marks a product or a category as pre-orderable while out of stock (deposit % + ETA). */
class PreorderRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['deposit_pct' => 'float', 'eta_days' => 'integer', 'active' => 'boolean'];
    }
}
