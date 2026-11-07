<?php

namespace App\Services\Commerce;

// Bangladesh mobile numbers, stored as +8801XXXXXXXXX (same rules as apps/web/src/components/checkout/cart-math.ts).
final class Phone
{
    /** Normalised +8801XXXXXXXXX, or null when the input is not a valid BD mobile number. */
    public static function normalize(?string $raw): ?string
    {
        $d = preg_replace('/\D/', '', (string) $raw);
        if (str_starts_with($d, '880')) {
            $d = substr($d, 3);
        }
        if (str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }

        return preg_match('/^1[3-9]\d{8}$/', $d) ? '+880'.$d : null;
    }

    /** 8801XXXXXXXXX (no plus) as most BD SMS gateways expect. */
    public static function msisdn(string $normalized): string
    {
        return ltrim($normalized, '+');
    }

    public static function mask(string $normalized): string
    {
        return substr($normalized, 0, 7).'****'.substr($normalized, -3);
    }
}
