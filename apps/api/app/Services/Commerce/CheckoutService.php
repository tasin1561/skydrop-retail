<?php

namespace App\Services\Commerce;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\Cms\FlashSaleService;
use App\Services\Cms\RestrictionService;
use App\Services\PricingService;

// Cart lines, coupons and totals. Same maths as apps/web/src/components/checkout/cart-math.ts.
class CheckoutService
{
    public const MARKETS = ['amazon', 'flipkart', 'bigbasket'];

    public function __construct(private PricingService $pricing, private PointsService $points) {}

    /**
     * Server-side lines for request items. unit_bdt is the CURRENT catalog price (client prices are ignored).
     *
     * @param  array<int, array{productId: string, variant?: ?string, qty: int, unitBdt?: mixed}>  $items
     * @return array<int, array<string, mixed>>
     */
    public function lines(array $items): array
    {
        $ids = array_values(array_unique(array_map(fn ($i) => (string) $i['productId'], $items)));
        $products = Product::query()->with('details')->whereIn('id', $ids)->get()->keyBy('id');
        $missing = array_values(array_diff($ids, $products->keys()->all()));
        if ($missing) {
            throw new CommerceException('Some items are no longer in our catalog.', 422, ['errors' => ['items' => ['Unknown product: '.implode(', ', $missing)]], 'missing' => $missing]);
        }

        $lines = array_map(function ($i) use ($products) {
            $p = $products[(string) $i['productId']];

            return [
                'product_id' => $p->id, 'market' => $p->market, 'category' => $p->category, 'title' => $p->title,
                'image' => $p->image, 'placeholder' => $p->placeholder, 'variant' => $i['variant'] ?? null,
                'qty' => (int) $i['qty'], 'client_bdt' => isset($i['unitBdt']) ? (float) $i['unitBdt'] : null,
                'unit_bdt' => (float) $this->pricing->productBdt($p),
                'unit_inr' => $this->pricing->buyInr($p),
                'weight_kg' => (float) $this->pricing->weightKg($p->details?->weight, (string) $p->category),
            ];
        }, array_values($items));

        // CMS hook: flash-sale info + per-order limit (line 'flash'), shipping restrictions (line 'restriction').
        return app(RestrictionService::class)->annotate(app(FlashSaleService::class)->lines($lines));
    }

    public function coupon(?string $code): ?Coupon
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $c = Coupon::query()->where('code', $code)->first();
        if (! $c || ! $c->usable()) {
            throw CommerceException::field('coupon', $c ? 'This coupon has expired.' : 'This coupon code is not valid.');
        }

        return $c;
    }

    public static function couponApi(Coupon $c): array
    {
        $kind = match ($c->type) {
            'free_shipping' => 'freeship', 'percent_items' => 'percent', default => 'fixed',
        };
        $label = match ($kind) {
            'freeship' => 'Free Skydrop shipping on every store',
            'percent' => rtrim(rtrim(number_format($c->value, 2), '0'), '.').'% off items'.($c->max_discount_bdt ? ', up to ৳'.number_format($c->max_discount_bdt) : ''),
            default => '৳'.number_format($c->value).' off items',
        };

        return ['code' => $c->code, 'label' => $label, 'kind' => $kind, 'value' => (float) $c->value,
            'maxDiscountBdt' => $c->max_discount_bdt !== null ? (int) $c->max_discount_bdt : null, 'minSubtotalBdt' => (int) $c->min_subtotal_bdt];
    }

    /**
     * Totals for lines (uses each line's unit_bdt).
     *
     * @return array<string, mixed>
     */
    public function quote(array $lines, ?Coupon $coupon = null, ?User $user = null, int $redeemPoints = 0): array
    {
        $byMarket = [];
        foreach ($lines as $l) {
            $byMarket[$l['market']][] = $l;
        }
        uksort($byMarket, fn ($a, $b) => $this->marketRank($a) <=> $this->marketRank($b));

        $groups = [];
        foreach ($byMarket as $market => $list) {
            $groups[] = [
                'market' => $market,
                'count' => array_sum(array_column($list, 'qty')),
                'subtotal' => (int) round(array_sum(array_map(fn ($l) => $l['unit_bdt'] * $l['qty'], $list))),
                'shipping' => $this->pricing->shipping(array_map(fn ($l) => ['unit_bdt' => $l['unit_bdt'], 'qty' => $l['qty'], 'weight_kg' => $l['weight_kg']], $list)),
                'weightKg' => round(array_sum(array_map(fn ($l) => $l['weight_kg'] * $l['qty'], $list)), 3),
            ];
        }
        $subtotal = array_sum(array_column($groups, 'subtotal'));
        $shipping = array_sum(array_column($groups, 'shipping'));

        $itemDiscount = 0;
        $shipDiscount = 0;
        if ($coupon) {
            if ($subtotal < (float) $coupon->min_subtotal_bdt) {
                throw CommerceException::field('coupon', 'Add ৳'.number_format($coupon->min_subtotal_bdt - $subtotal).' more to use this coupon.');
            }
            if ($coupon->type === 'percent_items') {
                $itemDiscount = (int) round($subtotal * $coupon->value / 100);
                if ($coupon->max_discount_bdt !== null) {
                    $itemDiscount = (int) min($itemDiscount, $coupon->max_discount_bdt);
                }
            } elseif ($coupon->type === 'free_shipping') {
                $shipDiscount = $shipping;
            } else {
                $itemDiscount = (int) min($subtotal, round($coupon->value));
            }
        }

        $plan = $this->points->plan($user, $redeemPoints, max(0, $subtotal - $itemDiscount));
        $discount = $itemDiscount + $shipDiscount + $plan['discount'];
        $total = max(0, $subtotal + $shipping - $discount);

        return [
            'groups' => $groups,
            'count' => array_sum(array_column($groups, 'count')),
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'itemDiscount' => $itemDiscount,
            'shipDiscount' => $shipDiscount,
            'couponDiscount' => $itemDiscount + $shipDiscount,
            'pointsRedeemed' => $plan['points'],
            'pointsDiscount' => $plan['discount'],
            'maxRedeemPoints' => $plan['maxPoints'],
            'pointsToEarn' => $this->points->toEarn($subtotal - $itemDiscount - $plan['discount']),
            'discount' => $discount,
            'total' => (int) $total,
            'coupon' => $coupon ? self::couponApi($coupon) : null,
            // CMS: flash savings already inside the subtotal, and warn/surcharge notices per item.
            'flashDiscount' => (int) array_sum(array_map(fn ($l) => (int) ($l['flash']['extraOffBdt'] ?? 0) * $l['qty'], $lines)),
            'restrictions' => array_values(array_filter(array_map(fn ($l) => empty($l['restriction']) ? null
                : ['productId' => $l['product_id'], 'title' => $l['title']] + $l['restriction'], $lines))),
        ];
    }

    private function marketRank(string $m): int
    {
        $i = array_search($m, self::MARKETS, true);

        return $i === false ? 99 : $i;
    }
}
