<?php

namespace App\Services\Commerce\Verify;

use App\Models\Product;
use App\Services\PricingService;

/** Verifies against the stored catalog price (no provider call). Used in tests and before the catalog module exists. */
class LocalPriceVerifier implements PriceVerifier
{
    public function __construct(private PricingService $pricing) {}

    public function verify(array $items): array
    {
        $products = Product::query()->whereIn('id', array_map(fn ($i) => (string) $i['productId'], $items))->get()->keyBy('id');
        $results = array_map(function ($i) use ($products) {
            $p = $products[(string) $i['productId']] ?? null;
            if (! $p) {
                return ['productId' => (string) $i['productId'], 'ok' => false, 'freshBdt' => null, 'changed' => false, 'deliverable' => false, 'reason' => 'not_found', 'provider' => 'local'];
            }
            $fresh = $this->pricing->productBdt($p);
            $available = $p->in_stock && ! $p->hidden;

            return [
                'productId' => $p->id, 'ok' => $available, 'freshBdt' => $fresh,
                'changed' => isset($i['unitBdt']) && (int) round((float) $i['unitBdt']) !== $fresh,
                'deliverable' => ! $p->hidden, 'reason' => $available ? null : 'out_of_stock', 'provider' => 'local',
            ];
        }, array_values($items));

        return ['ok' => collect($results)->every(fn ($r) => $r['ok']), 'checkedAt' => now()->toIso8601String(), 'results' => $results];
    }
}
