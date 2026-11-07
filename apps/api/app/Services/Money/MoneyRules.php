<?php

namespace App\Services\Money;

/** Request fields the money module adds to POST /checkout/quote and POST /orders. */
class MoneyRules
{
    public static function quote(): array
    {
        return [
            'useWallet' => ['nullable'], // true | false | BDT cap
            'payment' => ['nullable', 'string', 'max:20'],
            'emiTenure' => ['nullable', 'integer', 'min:1', 'max:36'],
            'emiBank' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** Options for CheckoutService::quote($lines, $coupon, $user, $points, $opts). */
    public static function opts(array $data, bool $strict = false): array
    {
        $w = $data['useWallet'] ?? false;
        $use = match (true) {
            is_bool($w) => $w,
            is_numeric($w) => (int) $w,
            in_array($w, ['true', 'on', 'yes'], true) => true,
            default => false,
        };

        return ['useWallet' => $use, 'payment' => $data['payment'] ?? null, 'emiTenure' => $data['emiTenure'] ?? null,
            'emiBank' => $data['emiBank'] ?? null, 'strict' => $strict];
    }
}
