<?php

namespace App\Services\Tracking;

use Illuminate\Http\Request;

/**
 * Marketing attribution sent by the storefront with POST /orders (`attribution`), stored on orders.attribution.
 * Whitelisted and length-capped; the client IP and user agent are added from the request.
 */
final class Attribution
{
    public const TOUCH_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'ttclid', 'landing', 'referrer', 'at'];

    public const IDS = ['fbp', 'fbc', 'ttp', 'gaClientId', 'consent'];

    /** Validation rules for the optional `attribution` object on POST /orders. */
    public static function rules(string $prefix = 'attribution'): array
    {
        return [$prefix => ['nullable', 'array']];
    }

    public static function sanitize(?array $raw): array
    {
        $raw ??= [];
        $out = [];
        foreach (['first', 'last'] as $touch) {
            if (! isset($raw[$touch]) || ! is_array($raw[$touch])) {
                continue;
            }
            $t = [];
            foreach (self::TOUCH_KEYS as $k) {
                $v = $raw[$touch][$k] ?? null;
                if (is_scalar($v) && (string) $v !== '') {
                    $t[$k] = mb_substr((string) $v, 0, in_array($k, ['landing', 'referrer'], true) ? 500 : 200);
                }
            }
            if ($t) {
                $out[$touch] = $t;
            }
        }
        foreach (self::IDS as $k) {
            $v = $raw[$k] ?? null;
            if (is_scalar($v) && (string) $v !== '') {
                $out[$k] = mb_substr((string) $v, 0, 200);
            }
        }
        if (isset($out['consent']) && ! in_array($out['consent'], ['all', 'essential'], true)) {
            unset($out['consent']);
        }

        return $out;
    }

    /** Sanitised attribution plus the client IP / user agent of this request (needed for CAPI matching). */
    public static function fromRequest(Request $request, ?array $raw): array
    {
        $a = self::sanitize($raw);
        $a['ip'] = $request->ip();
        $a['ua'] = mb_substr((string) $request->userAgent(), 0, 500) ?: null;

        return array_filter($a, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Source / medium / campaign for reports: last touch, then first touch; click ids and referrer as fallbacks.
     *
     * @return array{source: string, medium: ?string, campaign: ?string}
     */
    public static function source(?array $a): array
    {
        $a ??= [];
        foreach (['last', 'first'] as $touch) {
            $t = $a[$touch] ?? [];
            if (! empty($t['utm_source'])) {
                return ['source' => strtolower($t['utm_source']), 'medium' => $t['utm_medium'] ?? null, 'campaign' => $t['utm_campaign'] ?? null];
            }
            foreach (['fbclid' => 'facebook', 'gclid' => 'google', 'ttclid' => 'tiktok'] as $click => $src) {
                if (! empty($t[$click])) {
                    return ['source' => $src, 'medium' => 'paid', 'campaign' => null];
                }
            }
            if (! empty($t['referrer']) && ($host = parse_url($t['referrer'], PHP_URL_HOST))) {
                return ['source' => preg_replace('/^(www\.|m\.|l\.|lm\.)/', '', strtolower($host)), 'medium' => 'referral', 'campaign' => null];
            }
        }

        return ['source' => '(direct)', 'medium' => null, 'campaign' => null];
    }
}
