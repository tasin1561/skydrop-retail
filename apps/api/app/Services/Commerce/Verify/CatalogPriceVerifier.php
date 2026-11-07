<?php

namespace App\Services\Commerce\Verify;

/**
 * Delegates to the catalog module's App\Services\Catalog\CatalogService::verify($items) (live provider call per
 * item, pincode 700001). Falls back to the local catalog price when the catalog module is not installed yet.
 * The result is normalised to the documented shape whatever casing the catalog returns.
 */
class CatalogPriceVerifier implements PriceVerifier
{
    public const CATALOG = 'App\\Services\\Catalog\\CatalogService';

    public function __construct(private LocalPriceVerifier $local) {}

    public function verify(array $items): array
    {
        if (! class_exists(self::CATALOG) || ! method_exists(self::CATALOG, 'verify')) {
            return $this->local->verify($items);
        }
        $raw = app(self::CATALOG)->verify($items);
        $raw = $raw instanceof \JsonSerializable ? $raw->jsonSerialize() : (array) $raw;
        $results = array_map(function ($r) {
            $r = (array) $r;
            $fresh = $r['freshBdt'] ?? $r['fresh_bdt'] ?? null;

            return [
                'productId' => (string) ($r['productId'] ?? $r['product_id'] ?? ''),
                'ok' => (bool) ($r['ok'] ?? false),
                'freshBdt' => $fresh !== null ? (int) round((float) $fresh) : null,
                'changed' => (bool) ($r['changed'] ?? false),
                'deliverable' => (bool) ($r['deliverable'] ?? true),
                'reason' => $r['reason'] ?? null,
                'provider' => $r['provider'] ?? $r['source'] ?? 'catalog',
                // Pass-throughs the storefront and staff use: expired coupon/deal, unverified pincode, cached-price acceptance.
                'offerExpired' => (bool) ($r['offerExpired'] ?? false),
                'pincodeVerified' => $r['pincodeVerified'] ?? null,
                'unverified' => (bool) ($r['unverified'] ?? false),
            ];
        }, array_values($raw['results'] ?? []));

        return [
            'ok' => (bool) ($raw['ok'] ?? collect($results)->every(fn ($r) => $r['ok'])),
            'checkedAt' => (string) ($raw['checkedAt'] ?? $raw['checked_at'] ?? now()->toIso8601String()),
            'results' => $results,
        ];
    }
}
