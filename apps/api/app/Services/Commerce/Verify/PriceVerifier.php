<?php

namespace App\Services\Commerce\Verify;

interface PriceVerifier
{
    /**
     * Live price/stock/deliverability check (docs/API.md "POST /checkout/verify").
     *
     * @param  array<int, array{productId: string, variant?: ?string, qty: int, unitBdt: int|float}>  $items
     * @return array{ok: bool, checkedAt: string, results: array<int, array{productId: string, ok: bool, freshBdt: ?int, changed: bool, deliverable: bool, reason?: ?string, provider?: ?string}>}
     */
    public function verify(array $items): array;
}
