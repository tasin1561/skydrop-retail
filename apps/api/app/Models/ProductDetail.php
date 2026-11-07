<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDetail extends Model
{
    protected $primaryKey = 'product_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'images' => 'array', 'videos' => 'array', 'bullets' => 'array', 'specs' => 'array', 'variants' => 'array',
            'aplus' => 'array', 'reviews' => 'array', 'star_breakdown' => 'array', 'category_path' => 'array', 'offers' => 'array',
            'has_videos' => 'boolean',
        ];
    }
}
