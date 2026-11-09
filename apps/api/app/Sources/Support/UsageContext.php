<?php

namespace App\Sources\Support;

use Closure;

/**
 * Why a live retrieval call is being made, for the "Product data usage" dashboard. ApiLogger stamps the current
 * purpose and retry flag on every api_logs row. Callers wrap work in run(); an outer purpose wins over an inner
 * default (a staff refresh of a product page logs "refresh", not "product"), and a retry flag set anywhere above
 * stays set for everything below it.
 */
final class UsageContext
{
    /** Stored purpose => plain label shown to staff. */
    public const PURPOSES = [
        'search' => 'Search results',
        'product' => 'Product pages',
        'variant' => 'Product variants (sizes, colours)',
        'verify' => 'Checkout price checks',
        'refresh' => 'Staff refreshes',
        'watch' => 'Price checks for unpurchased orders',
        'paste-link' => 'Pasted product links',
        'import' => 'Catalog imports (bestsellers, deals, categories)',
    ];

    private static ?string $purpose = null;

    private static bool $retry = false;

    /**
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public static function run(?string $purpose, bool $retry, Closure $fn, bool $force = false): mixed
    {
        [$oldPurpose, $oldRetry] = [self::$purpose, self::$retry];
        if ($purpose !== null && ($force || self::$purpose === null)) {
            self::$purpose = $purpose;
        }
        self::$retry = $oldRetry || $retry;
        try {
            return $fn();
        } finally {
            [self::$purpose, self::$retry] = [$oldPurpose, $oldRetry];
        }
    }

    public static function purpose(): ?string
    {
        return self::$purpose;
    }

    public static function retry(): bool
    {
        return self::$retry;
    }

    public static function label(?string $purpose): string
    {
        return self::PURPOSES[$purpose] ?? 'Other';
    }

    /** Purpose for an older row (or a call made outside any context), from the logged call type. */
    public static function fromType(string $type): ?string
    {
        return match ($type) {
            'search' => 'search',
            'product' => 'product',
            'bestsellers', 'deals' => 'import',
            default => null,
        };
    }
}
