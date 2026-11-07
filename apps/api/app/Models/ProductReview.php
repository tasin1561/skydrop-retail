<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $table = 'product_reviews';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'verified_purchase' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        $name = trim((string) $this->user?->name);
        $parts = preg_split('/\s+/', $name) ?: [];
        $author = $parts ? $parts[0].(count($parts) > 1 ? ' '.mb_substr(end($parts), 0, 1).'.' : '') : 'Customer';

        return [
            'id' => (string) $this->id, 'productId' => $this->product_id, 'author' => $author, 'rating' => $this->rating,
            'title' => (string) $this->title, 'content' => $this->body, 'date' => $this->created_at?->toIso8601String(),
            'verifiedPurchase' => $this->verified_purchase, 'status' => $this->status,
        ];
    }
}
