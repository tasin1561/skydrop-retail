<?php

namespace App\Services\Finance;

use App\Models\ApiLog;
use App\Models\FxPaidRate;
use App\Models\Order;
use App\Models\Product;
use App\Support\Knobs;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Real profit per order and in aggregate.
 *
 * Revenue  = what the customer was charged: items + shipping + fees − discounts (coupon, bundle, membership…) − points.
 *            (= orders.total_bdt; the points discount is shown as a revenue deduction, not counted again as a cost.)
 * Costs    = purchase INR (order_items.actual_paid_inr, else unit_inr × qty, flagged "estimated") × FX actually paid
 *            + allocated Oxylabs/API spend + gateway fees + Skydrop shipping cost + unboxing cost + refunds.
 * FX gain  = purchase INR × (FX used to price the order − FX actually paid).
 */
class ProfitService
{
    /** @var array<string, float> month => BDT per order */
    private array $apiPerOrder = [];

    /** @var array<string, ?float> */
    private array $fxDay = [];

    /** @var array<string, string> product id => category */
    private array $categories = [];

    /** @return array<string, mixed> */
    public function forOrder(Order $order): array
    {
        $order->loadMissing(['items', 'payments']);
        $a = $order->getAttributes();

        $items = (float) $order->subtotal_bdt;
        $shipping = (float) $order->shipping_bdt;
        $fees = (float) ($a['unboxing_fee_bdt'] ?? 0) + (float) ($a['emi_charge_bdt'] ?? 0);
        $points = (float) ($order->points_discount_bdt ?? 0);
        $discounts = max(0, (float) $order->discount_bdt - $points);
        $revenue = (float) $order->total_bdt;

        $fxPaid = $this->fxPaid($order);
        $fxOrder = (float) ($order->fx_rate ?: $fxPaid);
        $inr = 0.0;
        $estimated = false;
        $lines = [];
        foreach ($order->items as $item) {
            $lineInr = $item->actual_paid_inr !== null ? (float) $item->actual_paid_inr : (float) $item->unit_inr * (int) $item->qty;
            $estimated = $estimated || $item->actual_paid_inr === null;
            $inr += $lineInr;
            $lines[] = ['item_id' => $item->id, 'market' => $item->market, 'category' => $this->category($item->product_id),
                'title' => $item->title, 'line_bdt' => (float) $item->unit_bdt * (int) $item->qty, 'inr' => $lineInr];
        }
        $cogs = $inr * $fxPaid;
        $fxGain = $inr * ($fxOrder - $fxPaid);

        $api = $this->apiCostPerOrder($order->created_at ?? now());

        $gateway = 0.0;
        $refunds = 0.0;
        foreach ($order->payments as $p) {
            if (in_array($p->status, ['paid', 'refunded'], true)) {
                $gateway += (float) $p->amount_bdt * $this->feePct($p->gateway, (string) ($p->kind ?? '')) / 100;
            }
            $refunds += (float) ($p->refunded_bdt ?? 0);
        }

        $kg = isset($a['actual_weight_kg']) && $a['actual_weight_kg'] !== null
            ? (float) $a['actual_weight_kg']
            : (float) $order->items->sum(fn ($i) => (float) $i->weight_kg * (int) $i->qty);
        $shipCost = $order->items->isEmpty() ? 0.0 : Knobs::float('finance.skydrop_cost.base_bdt', 120) + Knobs::float('finance.skydrop_cost.per_kg_bdt', 330) * max(0.5, $kg);
        $unboxing = ! empty($a['unboxing']) ? Knobs::float('finance.unboxing_cost_bdt', 40) : 0.0;

        $costs = $cogs + $api + $gateway + $shipCost + $unboxing + $refunds;
        $profit = $revenue - $costs;

        // Allocate order-level money to lines by item value (for store / category breakdowns).
        $sumLines = array_sum(array_column($lines, 'line_bdt')) ?: 1;
        foreach ($lines as &$l) {
            $share = $l['line_bdt'] / $sumLines;
            $l['revenue'] = $revenue * $share;
            $l['cogs'] = $l['inr'] * $fxPaid;
            $l['profit'] = $l['revenue'] - $l['cogs'] - ($api + $gateway + $shipCost + $unboxing + $refunds) * $share;
        }
        unset($l);

        return [
            'order_id' => $order->id, 'date' => $order->created_at?->toDateString(), 'status' => $order->status,
            'items_bdt' => $items, 'shipping_bdt' => $shipping, 'fees_bdt' => $fees, 'discounts_bdt' => $discounts, 'points_bdt' => $points,
            'revenue' => round($revenue, 2),
            'purchase_inr' => round($inr, 2), 'fx_order' => $fxOrder, 'fx_paid' => $fxPaid, 'estimated' => $estimated,
            'cogs' => round($cogs, 2), 'fx_gain' => round($fxGain, 2), 'api_cost' => round($api, 2), 'gateway_fees' => round($gateway, 2),
            'shipping_cost' => round($shipCost, 2), 'shipping_profit' => round($shipping - $shipCost, 2), 'weight_kg' => round($kg, 3),
            'unboxing_cost' => round($unboxing, 2), 'refunds' => round($refunds, 2),
            'costs' => round($costs, 2), 'profit' => round($profit, 2), 'margin_pct' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0.0,
            'lines' => $lines,
        ];
    }

    /**
     * @param  array{store?: ?string, category?: ?string}  $filters
     * @return array{kpis: array<string, float>, orders: Collection<int, array>, by_store: array, by_category: array, by_day: array}
     */
    public function report(Carbon $from, Carbon $to, array $filters = []): array
    {
        $store = $filters['store'] ?? null;
        $category = $filters['category'] ?? null;

        $rows = collect();
        Order::query()->with(['items', 'payments'])->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($store, fn ($q) => $q->whereHas('items', fn ($i) => $i->where('market', $store)))
            ->orderBy('created_at')
            ->chunk(200, function ($orders) use (&$rows) {
                foreach ($orders as $o) {
                    $rows->push($this->forOrder($o));
                }
            });

        if ($category) {
            $rows = $rows->filter(fn ($r) => collect($r['lines'])->contains('category', $category))->values();
        }

        $byStore = [];
        $byCategory = [];
        $byDay = [];
        foreach ($rows as $r) {
            foreach ($r['lines'] as $l) {
                if (($store && $l['market'] !== $store) || ($category && $l['category'] !== $category)) {
                    continue;
                }
                $s = $l['market'];
                $c = $l['category'] ?: 'other';
                $byStore[$s]['revenue'] = ($byStore[$s]['revenue'] ?? 0) + $l['revenue'];
                $byStore[$s]['profit'] = ($byStore[$s]['profit'] ?? 0) + $l['profit'];
                $byCategory[$c]['revenue'] = ($byCategory[$c]['revenue'] ?? 0) + $l['revenue'];
                $byCategory[$c]['profit'] = ($byCategory[$c]['profit'] ?? 0) + $l['profit'];
            }
            $d = $r['date'];
            $byDay[$d]['revenue'] = ($byDay[$d]['revenue'] ?? 0) + $r['revenue'];
            $byDay[$d]['profit'] = ($byDay[$d]['profit'] ?? 0) + $r['profit'];
        }
        ksort($byDay);

        $sum = fn (string $k) => round((float) $rows->sum($k), 2);
        $revenue = $sum('revenue');
        $profit = $sum('profit');

        return [
            'kpis' => [
                'orders' => $rows->count(), 'revenue' => $revenue, 'cogs' => $sum('cogs'), 'gross_profit' => round($revenue - $sum('cogs'), 2),
                'profit' => $profit, 'margin_pct' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0.0,
                'fx_gain' => $sum('fx_gain'), 'shipping_profit' => $sum('shipping_profit'), 'api_cost' => $sum('api_cost'),
                'gateway_fees' => $sum('gateway_fees'), 'refunds' => $sum('refunds'), 'points' => $sum('points_bdt'),
            ],
            'orders' => $rows,
            'by_store' => $byStore,
            'by_category' => $byCategory,
            'by_day' => $byDay,
        ];
    }

    public function feePct(?string $gateway, string $kind = ''): float
    {
        $g = strtolower((string) $gateway);
        if ($kind === 'balance' && ! in_array($g, ['bkash', 'nagad', 'sslcommerz', 'card'], true)) {
            $g = 'cod';
        }
        $default = (array) config('finance.gateway_fees');

        return (float) Knobs::get('finance.gateway_fees.'.$g, $default[$g] ?? 0);
    }

    /** BDT per INR actually paid: order override → that day's rate → global setting → FX used at order time. */
    public function fxPaid(Order $order): float
    {
        if ($order->fx_paid) {
            return (float) $order->fx_paid;
        }
        $day = ($order->created_at ?? now())->toDateString();
        if (! array_key_exists($day, $this->fxDay)) {
            $this->fxDay[$day] = FxPaidRate::query()->where('date', '<=', $day)->orderByDesc('date')->value('rate');
        }
        if ($this->fxDay[$day]) {
            return (float) $this->fxDay[$day];
        }
        $global = Knobs::float('finance.fx_paid', 0);

        return $global > 0 ? $global : (float) ($order->fx_rate ?: config('retail.pricing.fx_inr_bdt'));
    }

    /** Month's provider spend (USD → BDT) spread evenly over that month's non-cancelled orders. */
    public function apiCostPerOrder(\DateTimeInterface $at): float
    {
        $month = Carbon::instance($at)->format('Y-m');
        if (! isset($this->apiPerOrder[$month])) {
            $start = Carbon::parse($month.'-01')->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $usd = (float) ApiLog::query()->whereBetween('created_at', [$start, $end])->sum('cost_usd');
            $orders = Order::query()->where('status', '!=', 'cancelled')->whereBetween('created_at', [$start, $end])->count();
            $this->apiPerOrder[$month] = $orders > 0 ? $usd * Knobs::float('finance.usd_bdt', 122) / $orders : 0.0;
        }

        return $this->apiPerOrder[$month];
    }

    private function category(string $productId): string
    {
        return $this->categories[$productId] ??= (string) (Product::query()->whereKey($productId)->value('category') ?? 'other');
    }
}
