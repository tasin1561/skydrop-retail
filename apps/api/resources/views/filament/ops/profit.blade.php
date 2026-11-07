@php($d = $this->viewData())
@php($k = $d['k'])
<x-filament-panels::page>
    {{ $this->form }}

    <style>
        .ops-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem}
        .ops-kpi{border:1px solid rgba(127,127,127,.2);border-radius:.75rem;padding:.75rem 1rem}
        .ops-kpi b{display:block;font-size:1.25rem;margin-top:.15rem}
        .ops-kpi span{font-size:.75rem;opacity:.7}
        .ops-neg{color:#e11d48}.ops-pos{color:#059669}
        .ops-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem}
        .ops-card{border:1px solid rgba(127,127,127,.2);border-radius:.75rem;padding:1rem}
        .ops-card h3{font-weight:600;margin-bottom:.5rem}
        .ops-table{width:100%;font-size:.8rem;border-collapse:collapse}
        .ops-table th,.ops-table td{padding:.35rem .5rem;border-bottom:1px solid rgba(127,127,127,.15);text-align:right;white-space:nowrap}
        .ops-table th:first-child,.ops-table td:first-child{text-align:left}
    </style>

    <div class="ops-kpis">
        @foreach ([
            'Revenue' => $k['revenue'], 'COGS (FX paid)' => $k['cogs'], 'Gross profit' => $k['gross_profit'], 'Net profit' => $k['profit'],
            'FX gain / loss' => $k['fx_gain'], 'Shipping profit / loss' => $k['shipping_profit'], 'API cost' => $k['api_cost'],
            'Gateway fees' => $k['gateway_fees'], 'Refunds' => $k['refunds'],
        ] as $label => $v)
            <div class="ops-kpi"><span>{{ $label }}</span><b class="{{ in_array($label, ['Net profit', 'Gross profit', 'FX gain / loss', 'Shipping profit / loss']) ? ($v < 0 ? 'ops-neg' : 'ops-pos') : '' }}">৳{{ number_format($v) }}</b></div>
        @endforeach
        <div class="ops-kpi"><span>Margin</span><b class="{{ $k['margin_pct'] < 0 ? 'ops-neg' : 'ops-pos' }}">{{ $k['margin_pct'] }}%</b></div>
        <div class="ops-kpi"><span>Orders</span><b>{{ number_format($k['orders']) }}</b></div>
        @if (($k['plus_revenue'] ?? 0) > 0)
            <div class="ops-kpi"><span>Skydrop Plus revenue (in total)</span><b>৳{{ number_format($k['plus_revenue']) }}</b></div>
        @endif
        @if (($k['gift_card_sales'] ?? 0) > 0)
            <div class="ops-kpi" title="Gift card sales are a liability until redeemed; they are not counted as revenue"><span>Gift cards sold (liability)</span><b>৳{{ number_format($k['gift_card_sales']) }}</b></div>
        @endif
    </div>

    <div class="ops-card"><h3>Profit by day <small style="opacity:.6;font-weight:400">(bars = profit, line = revenue)</small></h3>{{ $d['dayChart'] }}</div>

    <div class="ops-grid">
        <div class="ops-card"><h3>By store</h3>{{ $d['storeChart'] }}</div>
        <div class="ops-card"><h3>By category</h3>{{ $d['categoryChart'] }}</div>
    </div>

    <div class="ops-card">
        <h3>Worst-margin orders</h3>
        <div style="overflow-x:auto">
        <table class="ops-table">
            <thead><tr><th>Order</th><th>Date</th><th>Revenue</th><th>COGS</th><th>Fees+API</th><th>Ship cost</th><th>Refunds</th><th>Profit</th><th>Margin</th></tr></thead>
            <tbody>
            @forelse ($d['worst'] as $o)
                <tr>
                    <td><a href="{{ ($d['orderUrl'])($o['order_id']) }}" style="color:#5b3df5;font-weight:600">{{ $o['order_id'] }}</a>@if ($o['estimated']) <span title="Purchase cost not entered yet; uses the catalog INR price" style="opacity:.6">est.</span>@endif</td>
                    <td>{{ $o['date'] }}</td>
                    <td>৳{{ number_format($o['revenue']) }}</td>
                    <td>৳{{ number_format($o['cogs']) }}</td>
                    <td>৳{{ number_format($o['gateway_fees'] + $o['api_cost']) }}</td>
                    <td>৳{{ number_format($o['shipping_cost']) }}</td>
                    <td>৳{{ number_format($o['refunds']) }}</td>
                    <td class="{{ $o['profit'] < 0 ? 'ops-neg' : '' }}">৳{{ number_format($o['profit']) }}</td>
                    <td class="{{ $o['margin_pct'] < 0 ? 'ops-neg' : '' }}">{{ $o['margin_pct'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;opacity:.6">No orders in this range.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</x-filament-panels::page>
