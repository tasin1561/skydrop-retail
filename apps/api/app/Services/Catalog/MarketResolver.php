<?php

namespace App\Services\Catalog;

use App\Models\Product;

/** Which marketplace an id belongs to: the stored row, else its shape (itm... Flipkart, bb123 BigBasket, my123 Myntra, ASIN Amazon). */
class MarketResolver
{
    public function forId(string $id): ?string
    {
        $stored = Product::query()->whereKey($id)->value('market');
        if ($stored) {
            return $stored;
        }

        return match (true) {
            (bool) preg_match('/^itm[a-z0-9]{6,}(-[a-z0-9]{8,20})?$/i', $id) => 'flipkart',
            (bool) preg_match('/^bb\d+$/i', $id) => 'bigbasket',
            (bool) preg_match('/^my\d{5,10}$/i', $id) => 'myntra',
            (bool) preg_match('/^[A-Z0-9]{10}$/', $id) => 'amazon',
            default => null,
        };
    }
}
