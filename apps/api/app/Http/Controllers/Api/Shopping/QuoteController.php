<?php

namespace App\Http\Controllers\Api\Shopping;

use App\Http\Controllers\Controller;
use App\Services\PricingService;
use Illuminate\Http\Request;

/**
 * Cost calculator: "this costs ₹X in India, what do I pay in Bangladesh?" Uses the same pricing rules as product
 * pages and checkout (exchange rate, import charge, service fee, delivery), so the answer matches what an order costs.
 */
class QuoteController extends Controller
{
    private const CATEGORIES = ['electronics', 'fashion', 'beauty', 'grocery', 'home'];

    public function __invoke(Request $request, PricingService $pricing): array
    {
        $v = $request->validate([
            'inr' => ['required', 'numeric', 'min:1', 'max:2000000'],
            'category' => ['nullable', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'weight_kg' => ['nullable', 'numeric', 'min:0.01', 'max:60'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $category = $v['category'] ?? 'fashion';
        $qty = (int) ($v['qty'] ?? 1);
        $weight = isset($v['weight_kg']) ? (float) $v['weight_kg'] : $pricing->weightKg(null, $category);
        $b = $pricing->breakdown((float) $v['inr'], $category);
        $shipping = $pricing->shipping([['unit_bdt' => $b['total'], 'qty' => $qty, 'weight_kg' => $weight]]);
        $items = $b['total'] * $qty;

        return [
            'category' => $category,
            'qty' => $qty,
            'weightKg' => round($weight, 2),
            'weightAssumed' => ! isset($v['weight_kg']),
            'unit' => ['storeBdt' => $b['store'], 'importBdt' => $b['import'], 'serviceBdt' => $b['service'], 'totalBdt' => $b['total']],
            'itemsBdt' => $items,
            'shippingBdt' => $shipping,
            'totalBdt' => $items + $shipping,
            'fx' => round($pricing->fx(), 4),
            'freeShippingAboveBdt' => (int) config('retail.pricing.shipping.free_above_bdt'),
        ];
    }
}
