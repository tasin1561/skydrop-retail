<?php

namespace App\Sources\Support;

use Carbon\CarbonImmutable;

/**
 * Offer records shared by all parsers:
 * {type, title, detail?, savingInr?, appliesToOurPrice, endsAt?, claimedPct?}.
 * appliesToOurPrice is true for offers we can use when we buy (coupons, deal prices) and false for
 * payment-specific ones (bank, card, EMI, exchange) which are shown "on the source store" only.
 */
final class Offers
{
    public const TYPES = ['coupon', 'deal', 'lightning', 'bank', 'cashback', 'emi', 'partner', 'combo',
        'special_price', 'subscribe', 'exchange', 'other'];

    private const APPLIES = ['coupon' => true, 'deal' => true, 'lightning' => true, 'special_price' => true];

    /** @return array<string, mixed> */
    public static function make(string $type, string $title, ?string $detail = null, ?float $saving = null,
        ?CarbonImmutable $endsAt = null, ?int $claimedPct = null, ?bool $applies = null): array
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'other';

        return array_filter([
            'type' => $type,
            'title' => Text::clean($title),
            'detail' => $detail !== null && $detail !== '' ? Text::truncate(Text::clean($detail), 300) : null,
            'savingInr' => $saving !== null && $saving > 0 ? round($saving, 2) : null,
            'appliesToOurPrice' => $applies ?? (self::APPLIES[$type] ?? false),
            'endsAt' => $endsAt?->toIso8601String(),
            'claimedPct' => $claimedPct,
        ], fn ($v) => $v !== null);
    }

    /** Map a store's offer heading ("Bank Offer", "No Cost EMI", "Special Price"...) to our type. */
    public static function classify(string $title): string
    {
        $t = strtolower($title);

        return match (true) {
            str_contains($t, 'lightning') => 'lightning',
            str_contains($t, 'coupon') => 'coupon',
            str_contains($t, 'cashback') => 'cashback',
            str_contains($t, 'emi') => 'emi',
            str_contains($t, 'bank') || str_contains($t, 'card') => 'bank',
            str_contains($t, 'exchange') => 'exchange',
            str_contains($t, 'special price') => 'special_price',
            str_contains($t, 'combo') || (bool) preg_match('/buy\s*\d+\s*get\s*\d+|bogo/', $t) => 'combo',
            str_contains($t, 'partner') => 'partner',
            str_contains($t, 'subscribe') => 'subscribe',
            str_contains($t, 'deal') => 'deal',
            default => 'other',
        };
    }

    /** First rupee amount in a sentence ("Upto ₹100.00 discount" -> 100). */
    public static function rupees(string $text): ?float
    {
        return preg_match('/(?:₹|Rs\.?|INR)\s*(\d[\d,]*(?:\.\d+)?)/u', $text, $m) ? (float) str_replace(',', '', $m[1]) : null;
    }

    /**
     * Coupon value in INR from text like "Apply ₹500 coupon", "Save 10% with coupon" or a numeric/percent pair.
     */
    public static function couponValue(string $text, float $price): ?float
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $text, $m)) {
            return round($price * (float) $m[1] / 100, 2);
        }

        return self::rupees($text);
    }

    /**
     * The price we can actually buy at: deal price minus a clippable coupon (never below 1).
     *
     * @param  list<array<string, mixed>>  $offers
     */
    public static function effectivePrice(float $price, ?float $coupon): ?float
    {
        // A coupon worth most of the price is a misread, not a deal: never price from it.
        if ($price <= 0 || ! $coupon || $coupon > 0.6 * $price) {
            return null;
        }

        return max(1.0, round($price - $coupon, 2));
    }

    /** "Ends in 02:13:44", "Ends in 3h 20m", "Ends in 45 mins" -> absolute time. */
    public static function endsIn(string $text, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $now ??= CarbonImmutable::now();
        if (preg_match('/ends?\s+in\s*:?\s*(\d{1,2}):(\d{2})(?::(\d{2}))?/i', $text, $m)) {
            return $now->addHours((int) $m[1])->addMinutes((int) $m[2])->addSeconds((int) ($m[3] ?? 0));
        }
        if (preg_match('/ends?\s+in\s*:?\s*(?:(\d+)\s*d(?:ays?)?\s*)?(?:(\d+)\s*h(?:rs?|ours?)?\s*)?(?:(\d+)\s*m(?:ins?|inutes?)?)?/i', $text, $m)
            && (($m[1] ?? '') !== '' || ($m[2] ?? '') !== '' || ($m[3] ?? '') !== '')) {
            return $now->addDays((int) ($m[1] ?? 0))->addHours((int) ($m[2] ?? 0))->addMinutes((int) ($m[3] ?? 0));
        }

        return null;
    }
}
