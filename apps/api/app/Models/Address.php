<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $table = 'addresses';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Web `Address` shape. */
    public function toApi(): array
    {
        return [
            'id' => (string) $this->id, 'name' => $this->name, 'phone' => $this->phone, 'division' => $this->division,
            'district' => $this->district, 'area' => $this->area, 'line' => $this->line, 'isDefault' => (bool) $this->is_default,
        ];
    }

    /** Frozen copy stored on orders. */
    public function snapshot(): array
    {
        return $this->toApi();
    }
}
