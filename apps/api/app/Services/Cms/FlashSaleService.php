<?php

namespace App\Services\Cms;

use App\Models\FlashSale;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Money\MembershipService;
use App\Services\PricingService;
use App\Support\Features;
use Illuminate\Support\Carbon;

/**
 * Flash sales: an extra Skydrop discount (percent or fixed BDT) on chosen products for a time window.
 * Funded from our margin, so the discount never exceeds the margin part of the price.
 * Applied inside PricingService::productBdt, so catalog, verify, quote and orders all agree.
 *
 * Skydrop Plus benefit `early_flash_sale_access_minutes`: the signed-in member (API token / session) sees and buys
 * a sale that many minutes before starts_at. Everyone else is unaffected.
 */
class FlashSaleService
{
    public function __construct(private PricingService $pricing) {}

    /**
     * product id => offers of live or upcoming sales (cached).
     *
     * @return array<string, array<int, array{saleId: int, title: string, store: string, startsAt: int, endsAt: int, maxQty: ?int, offPct: ?float, offBdt: ?int}>>
     */
    public function index(): array
    {
        return CmsCache::remember(CmsCache::FLASH, function () {
            $map = [];
            $sales = FlashSale::query()->with('items')->where('active', true)->where('ends_at', '>', now())->get();
            foreach ($sales as $s) {
                foreach ($s->items as $it) {
                    $map[$it->product_id][] = [
                        'saleId' => $s->id, 'title' => $s->title, 'store' => $s->store,
                        'startsAt' => $s->starts_at->getTimestamp(), 'endsAt' => $s->ends_at->getTimestamp(),
                        'maxQty' => $s->max_qty, 'offPct' => $it->off_pct, 'offBdt' => $it->off_bdt,
                    ];
                }
            }

            return $map;
        });
    }

    /**
     * The live flash offer for a product given its regular Skydrop price (BDT), or null.
     *
     * @return array{saleId: int, title: string, endsAt: string, extraOffBdt: int, maxQty: ?int}|null
     */
    public function offer(object $p, int $baseBdt): ?array
    {
        if (! Features::enabled('flash_sales')) {
            return null;
        }
        $id = (string) ($p->id ?? '');
        $list = $id !== '' ? ($this->index()[$id] ?? []) : [];
        if (! $list || $baseBdt <= 0) {
            return null;
        }
        $now = now()->getTimestamp();
        // Only look the viewer up when an upcoming sale could open early for them.
        $early = collect($list)->contains(fn ($o) => $o['startsAt'] > $now && $o['endsAt'] > $now) ? $this->earlyMinutes() * 60 : 0;
        $best = null;
        foreach ($list as $o) {
            if ($now < $o['startsAt'] - $early || $o['endsAt'] <= $now || ($o['store'] !== 'all' && $o['store'] !== ($p->market ?? null))) {
                continue;
            }
            $off = $this->amount($o, $baseBdt, (string) ($p->category ?? ''));
            if ($off > 0 && (! $best || $off > $best['extraOffBdt'])) {
                $best = ['saleId' => $o['saleId'], 'title' => $o['title'], 'endsAt' => Carbon::createFromTimestamp($o['endsAt'])->toIso8601String(),
                    'extraOffBdt' => $off, 'maxQty' => $o['maxQty']];
            }
        }

        return $best;
    }

    /**
     * Minutes of early access for $user (default: the signed-in API user). Memoized per request.
     */
    public function earlyMinutes(?User $user = null): int
    {
        $user ??= rescue(fn () => auth('sanctum')->user(), null, false);
        if (! $user instanceof User) {
            return 0;
        }
        $request = rescue(fn () => request(), null, false);
        $key = 'flash.early.'.$user->getKey();
        $memo = $request?->attributes->get($key);
        if (is_array($memo) && $memo[1] > time()) {
            return $memo[0];
        }
        $minutes = (int) rescue(fn () => app(MembershipService::class)->benefitsFor($user)['early_flash_sale_access_minutes'] ?? 0, 0, false);
        $request?->attributes->set($key, [$minutes, time() + 60]);

        return $minutes;
    }

    /** Is the sale open for this viewer (live, or inside their early-access window)? */
    public function openFor(FlashSale $sale, ?User $user = null): bool
    {
        if (! $sale->active || $sale->ends_at->lte(now())) {
            return false;
        }

        return $sale->starts_at->copy()->subMinutes($this->earlyMinutes($user))->lte(now());
    }

    /** Discount in BDT, capped at our margin on this price. */
    public function amount(array $o, int $baseBdt, string $category): int
    {
        $raw = $o['offPct'] ? $baseBdt * $o['offPct'] / 100 : (int) ($o['offBdt'] ?? 0);
        $margin = $this->pricing->rule($category)['margin'];
        $cap = (int) floor($baseBdt - $baseBdt / (1 + $margin));

        return (int) max(0, min((int) round($raw), $cap));
    }

    /** Product JSON fragment: `flashSale: { saleId, title, endsAt, extraOffBdt, maxQty } | null`. */
    public function forProduct(object $p): ?array
    {
        return $this->offer($p, $this->pricing->basePriceBdt($p));
    }

    /**
     * Checkout hook: annotate lines with the flash discount and enforce the per-order max qty.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function lines(array $lines): array
    {
        $products = Product::query()->whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');
        $qty = [];
        foreach ($lines as $l) {
            $qty[$l['product_id']] = ($qty[$l['product_id']] ?? 0) + (int) $l['qty'];
        }
        foreach ($lines as $k => $l) {
            $p = $products[$l['product_id']] ?? null;
            $offer = $p ? $this->forProduct($p) : null;
            $lines[$k]['flash'] = $offer;
            if ($offer && $offer['maxQty'] && $qty[$l['product_id']] > $offer['maxQty']) {
                throw new CommerceException("Flash sale limit: up to {$offer['maxQty']} per order for \"".mb_strimwidth($l['title'], 0, 60, '…').'".', 422,
                    ['errors' => ['items' => ["Up to {$offer['maxQty']} per order at the flash price."]], 'productId' => $l['product_id'], 'maxQty' => $offer['maxQty']],
                    'flash_limit');
            }
        }

        return $lines;
    }
}
