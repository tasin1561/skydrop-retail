<?php

namespace App\Services\Assistant;

/** Removes personal data (phone numbers, emails, card-like numbers) before text is stored or logged. */
final class Pii
{
    public static function scrub(string $text): string
    {
        $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $text) ?? $text;
        // Bangladeshi mobiles (+8801XXXXXXXXX / 01XXXXXXXXX, with spaces/dashes) and Bangla digits.
        $text = preg_replace('/(?:\+?88[\s-]?)?0?1[3-9](?:[\s-]?\d){8}\b/u', '[phone]', $text) ?? $text;
        $text = preg_replace('/(?:\+?৮৮)?০?১[৩-৯][০-৯\s-]{8,11}/u', '[phone]', $text) ?? $text;
        // Long digit runs (cards, bKash transaction pins, NID): 12+ digits.
        $text = preg_replace('/\b(?:\d[\s-]?){12,19}\b/', '[number]', $text) ?? $text;

        return $text;
    }

    /** Masks a stored phone for display: +8801712345678 -> +88017•••••678 */
    public static function maskPhone(?string $phone): string
    {
        if (! $phone) {
            return '—';
        }
        $len = strlen($phone);

        return $len <= 6 ? str_repeat('•', $len) : substr($phone, 0, 6).str_repeat('•', max(0, $len - 9)).substr($phone, -3);
    }
}
