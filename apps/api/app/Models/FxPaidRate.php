<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** BDT per INR we actually paid on a given day (profit report). */
class FxPaidRate extends Model
{
    protected $table = 'fx_paid_rates';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'rate' => 'float'];
    }
}
