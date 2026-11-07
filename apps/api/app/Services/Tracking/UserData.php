<?php

namespace App\Services\Tracking;

use App\Models\User;
use App\Services\Commerce\Phone;

/**
 * Normalises and SHA-256 hashes customer data for the ad platforms (Meta CAPI / TikTok Events API rules).
 * Plain PII never leaves this class: only hashes go into the event payload and the tracking_events log.
 */
final class UserData
{
    public static function hash(?string $value): ?string
    {
        $v = trim((string) $value);

        return $v === '' ? null : hash('sha256', $v);
    }

    /** BD mobile → 8801XXXXXXXXX (digits, country code, no plus). Null when not a valid BD mobile. */
    public static function normalizePhone(?string $raw): ?string
    {
        $n = Phone::normalize($raw);

        return $n ? Phone::msisdn($n) : null;
    }

    public static function normalizeEmail(?string $raw): ?string
    {
        $e = strtolower(trim((string) $raw));

        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    public static function normalizeName(?string $raw): ?string
    {
        $n = mb_strtolower(trim((string) $raw));
        $n = preg_replace('/[^\p{L}]/u', '', $n);

        return $n === '' ? null : $n;
    }

    /**
     * Hashed identifiers: ph (8801…), ph_e164 (+8801…, TikTok), em, external_id, fn, ln, ct, country.
     *
     * @return array<string, string>
     */
    public static function fromParts(?string $phone = null, ?string $email = null, ?string $name = null, ?string $city = null, int|string|null $userId = null): array
    {
        $ph = self::normalizePhone($phone);
        $em = self::normalizeEmail($email);
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $fn = count($parts) ? self::normalizeName($parts[0]) : null;
        $ln = count($parts) > 1 ? self::normalizeName((string) end($parts)) : null;
        $ct = self::normalizeName($city);

        return array_filter([
            'ph' => $ph ? self::hash($ph) : null,
            'ph_e164' => $ph ? self::hash('+'.$ph) : null,
            'em' => $em ? self::hash($em) : null,
            'external_id' => $userId !== null && $userId !== '' ? self::hash('sr-user-'.$userId) : null,
            'fn' => $fn ? self::hash($fn) : null,
            'ln' => $ln ? self::hash($ln) : null,
            'ct' => $ct ? self::hash($ct) : null,
            'country' => self::hash('bd'),
        ]);
    }

    /** @return array<string, string> */
    public static function forUser(?User $user, ?array $address = null): array
    {
        if (! $user && ! $address) {
            return [];
        }

        return self::fromParts(
            $user?->phone ?? ($address['phone'] ?? null),
            $user?->email,
            $user?->name ?? ($address['name'] ?? null),
            $address['district'] ?? null,
            $user?->id,
        );
    }
}
