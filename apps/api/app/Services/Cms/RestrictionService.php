<?php

namespace App\Services\Cms;

use App\Models\Product;
use App\Models\RestrictedRule;
use App\Services\Commerce\CommerceException;
use App\Support\Features;

/**
 * "Can this ship to Bangladesh?" rules (table restricted_rules). block > surcharge > warn.
 * check() feeds product JSON (`restriction`), the price (surcharge) and order creation (block → 422).
 */
class RestrictionService
{
    private const RANK = ['block' => 3, 'surcharge' => 2, 'warn' => 1];

    /** @return array<int, array{id: int, type: string, pattern: string, action: string, message: string, surcharge: int}> */
    public function rules(): array
    {
        return CmsCache::remember(CmsCache::RULES, fn () => RestrictedRule::query()->where('active', true)->orderBy('sort')->orderBy('id')->get()
            ->map(fn (RestrictedRule $r) => ['id' => $r->id, 'type' => $r->type, 'pattern' => $r->pattern, 'action' => $r->action,
                'message' => $r->message, 'surcharge' => (int) $r->surcharge_bdt])->all());
    }

    /**
     * @return array{action: string, message: string, surchargeBdt: int, ruleId: int}|null
     */
    public function check(object $p): ?array
    {
        if (! Features::enabled('restrictions')) {
            return null;
        }
        $path = '';
        if ($p instanceof Product && $p->relationLoaded('details') && $p->details?->category_path) {
            $path = implode(' ', array_map('strval', (array) json_decode(json_encode($p->details->category_path), true)));
        }

        return $this->test((string) ($p->title ?? ''), (string) ($p->category ?? ''), (string) ($p->brand ?? ''), $path);
    }

    /** Same as check() for loose values (admin "test a title" box). */
    public function test(string $title, string $category = '', string $brand = '', string $categoryPath = '', ?array $rules = null): ?array
    {
        $best = null;
        foreach ($rules ?? $this->rules() as $r) {
            $subject = match ($r['type']) {
                'category' => trim($category.' '.$categoryPath),
                'brand' => $brand,
                default => $title,
            };
            if ($subject === '' || ! self::matches($r['pattern'], $subject, $r['type'] === 'brand')) {
                continue;
            }
            if (! $best || self::RANK[$r['action']] > self::RANK[$best['action']]) {
                $best = ['action' => $r['action'], 'message' => $r['message'], 'surchargeBdt' => $r['action'] === 'surcharge' ? $r['surcharge'] : 0, 'ruleId' => $r['id']];
            }
        }

        return $best;
    }

    /** Surcharge (BDT per unit) the price function adds for this product. */
    public function surchargeBdt(object $p): int
    {
        $r = $this->check($p);

        return $r && $r['action'] === 'surcharge' ? (int) $r['surchargeBdt'] : 0;
    }

    /** Product JSON fragment: `restriction: { action, message } | null`. */
    public function forProduct(object $p): ?array
    {
        $r = $this->check($p);

        return $r ? ['action' => $r['action'], 'message' => $r['message'], 'surchargeBdt' => $r['surchargeBdt']] : null;
    }

    /**
     * Order creation hook: blocked items → 422 { code: "restricted", restricted: [...] }.
     *
     * @param  array<int, array<string, mixed>>  $lines  CheckoutService::lines()
     */
    public function assertOrderable(array $lines): void
    {
        $blocked = [];
        foreach ($this->annotate($lines) as $l) {
            if (($l['restriction']['action'] ?? null) === 'block') {
                $blocked[] = ['productId' => $l['product_id'], 'title' => $l['title'], 'message' => $l['restriction']['message']];
            }
        }
        if ($blocked) {
            throw new CommerceException('Some items cannot be shipped to Bangladesh. Remove them to continue.', 422,
                ['restricted' => $blocked, 'errors' => ['items' => array_map(fn ($b) => $b['title'].': '.$b['message'], $blocked)]], 'restricted');
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function annotate(array $lines): array
    {
        $products = Product::query()->with('details')->whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');
        foreach ($lines as $k => $l) {
            $p = $products[$l['product_id']] ?? null;
            $lines[$k]['restriction'] = $p ? $this->forProduct($p) : null;
        }

        return $lines;
    }

    public static function matches(string $pattern, string $subject, bool $exact = false): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return false;
        }
        if (preg_match('#^/.+/[a-z]*$#s', $pattern)) {
            return (bool) @preg_match($pattern, $subject);
        }
        foreach (array_filter(array_map('trim', explode(',', $pattern))) as $word) {
            if ($exact ? mb_strtolower($word) === mb_strtolower(trim($subject)) : preg_match('/(?<![\pL\pN])'.preg_quote($word, '/').'(?![\pL\pN])/iu', $subject)) {
                return true;
            }
        }

        return false;
    }

    /** Is the stored pattern a valid regex (or a plain list)? Used by the admin form. */
    public static function validPattern(string $pattern): bool
    {
        $pattern = trim($pattern);
        if (! preg_match('#^/.+/[a-z]*$#s', $pattern)) {
            return $pattern !== '';
        }

        return @preg_match($pattern, '') !== false;
    }
}
