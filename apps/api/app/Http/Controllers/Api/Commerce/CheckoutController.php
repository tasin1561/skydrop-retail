<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Cms\RestrictionService;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\OrderLimits;
use App\Services\Commerce\Verify\PriceVerifier;
use App\Services\Money\MoneyRules;
use App\Support\Limits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public static function itemRules(): array
    {
        return [
            // Hard ceilings only; the admin-editable limits (System → Limits) are checked by OrderLimits with clear messages.
            'items' => ['required', 'array', 'min:1', 'max:'.Limits::REGISTRY['orders.max_items'][5]],
            'items.*.productId' => ['required', 'string', 'max:40'],
            'items.*.variant' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:'.Limits::REGISTRY['orders.max_qty'][5]],
            'items.*.unitBdt' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** Totals from server prices: groups per marketplace, shipping, coupon and points. */
    public function quote(Request $request, CheckoutService $checkout): JsonResponse
    {
        $data = $request->validate(self::itemRules() + [
            'coupon' => ['nullable', 'string', 'max:30'],
            'redeemPoints' => ['nullable', 'integer', 'min:0'],
            'unboxing' => ['nullable', 'boolean'], // fulfilment: paid unboxing at the Kolkata hub
        ] + MoneyRules::quote());
        $lines = $checkout->lines($data['items']);
        $quote = $checkout->quote($lines, $checkout->coupon($data['coupon'] ?? null), $request->user('sanctum'), (int) ($data['redeemPoints'] ?? 0),
            MoneyRules::opts($data) + ['unboxing' => (bool) ($data['unboxing'] ?? false)]);
        $quote['items'] = array_map(fn ($l) => ['productId' => $l['product_id'], 'variant' => $l['variant'], 'qty' => $l['qty'],
            'unitBdt' => (int) $l['unit_bdt'], 'weightKg' => $l['weight_kg'], 'market' => $l['market'],
            'changed' => $l['client_bdt'] !== null && (int) round($l['client_bdt']) !== (int) $l['unit_bdt'],
            'flashSale' => $l['flash'] ?? null, 'restriction' => $l['restriction'] ?? null], $lines);

        return response()->json($quote);
    }

    /** Live price/stock/deliverability check (delegates to the catalog module). */
    public function verify(Request $request, PriceVerifier $verifier): JsonResponse
    {
        $data = $request->validate(self::itemRules());
        app(OrderLimits::class)->assertCart($data['items']); // each item is a live provider call

        $result = $verifier->verify($data['items']);

        // Items that cannot ship to Bangladesh fail verification (restricted_rules, action=block).
        $rules = app(RestrictionService::class);
        $products = Product::query()->with('details')->whereIn('id', array_column($result['results'], 'productId'))->get()->keyBy('id');
        foreach ($result['results'] as $k => $r) {
            $hit = isset($products[$r['productId']]) ? $rules->forProduct($products[$r['productId']]) : null;
            if ($hit) {
                $result['results'][$k]['restriction'] = $hit;
            }
            if (($hit['action'] ?? null) === 'block') {
                $result['results'][$k] = array_merge($result['results'][$k], ['ok' => false, 'deliverable' => false, 'reason' => 'restricted', 'message' => $hit['message']]);
                $result['ok'] = false;
            }
        }

        return response()->json($result);
    }
}
