@php($d = $this->viewData())
@php($c = $d['close'])
<x-filament-panels::page>
    {{ $this->form }}
    <style>
        .ops-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem}
        .ops-card{border:1px solid rgba(127,127,127,.2);border-radius:.75rem;padding:1rem}
        .ops-card h3{font-weight:600;margin-bottom:.5rem}
        .ops-table{width:100%;font-size:.8rem;border-collapse:collapse}
        .ops-table th,.ops-table td{padding:.35rem .5rem;border-bottom:1px solid rgba(127,127,127,.15);text-align:right;white-space:nowrap}
        .ops-table th:first-child,.ops-table td:first-child{text-align:left}
        .ops-neg{color:#e11d48}.ops-warn{color:#d97706}
    </style>

    <div class="ops-grid">
        <div class="ops-card">
            <h3>Month close: {{ $c['month'] }}</h3>
            <table class="ops-table"><tbody>
                @foreach (['orders' => 'Goods orders', 'goods_revenue' => 'Net sales (goods)', 'plus_revenue' => 'Skydrop Plus revenue', 'revenue' => 'Total revenue', 'cogs' => 'Purchase cost (FX paid)', 'gateway_fees' => 'Gateway fees', 'api_cost' => 'Data/API cost', 'refunds' => 'Refunds', 'fx_gain' => 'FX gain / loss', 'shipping_profit' => 'Shipping profit / loss', 'profit' => 'Net profit', 'margin_pct' => 'Margin %'] as $key => $label)
                    <tr><td>{{ $label }}</td><td class="{{ ($c['kpis'][$key] ?? 0) < 0 ? 'ops-neg' : '' }}">{{ $key === 'margin_pct' ? $c['kpis'][$key].'%' : ($key === 'orders' ? $c['kpis'][$key] : '৳'.number_format($c['kpis'][$key])) }}</td></tr>
                @endforeach
                @if (($c['gift_card_sales']['amount'] ?? 0) > 0)
                    <tr><td>Gift cards sold ({{ $c['gift_card_sales']['count'] }})<br><small style="opacity:.6">Liability until redeemed, not revenue</small></td><td>৳{{ number_format($c['gift_card_sales']['amount']) }}</td></tr>
                @endif
                @if ($c['estimated_orders'] > 0)
                    <tr><td colspan="2" class="ops-warn" style="text-align:left">{{ $c['estimated_orders'] }} order(s) have no actual purchase amount yet (Purchase queue → Bought).</td></tr>
                @endif
            </tbody></table>
        </div>
        <div class="ops-card">
            <h3>Payments received (export range)</h3>
            <table class="ops-table"><thead><tr><th>Gateway</th><th>Count</th><th>Amount</th><th>Est. fees</th></tr></thead><tbody>
                @forelse ($d['gateways'] as $g => $v)
                    <tr><td>{{ $g }}</td><td>{{ $v['count'] }}</td><td>৳{{ number_format($v['amount']) }}</td><td>৳{{ number_format($v['fees']) }}</td></tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;opacity:.6">No payments in range.</td></tr>
                @endforelse
            </tbody></table>
        </div>
        <div class="ops-card">
            <h3>Liabilities (now)</h3>
            <table class="ops-table"><tbody>
                @foreach ($d['liabilities'] as $l)
                    <tr><td>{{ $l['label'] }}<br><small style="opacity:.6">{{ $l['note'] }}</small></td><td>৳{{ number_format($l['amount']) }}</td></tr>
                @endforeach
            </tbody></table>
        </div>
    </div>

    @if ($d['settlement'])
        @php($s = $d['settlement'])
        <div class="ops-card">
            <h3>Settlement reconciliation</h3>
            @if (isset($s['error']))
                <p class="ops-neg">{{ $s['error'] }}</p>
            @else
                <p style="font-size:.85rem">{{ $s['totals']['rows'] }} rows · <b>{{ $s['totals']['matched'] }}</b> matched ·
                    <b class="ops-neg">{{ $s['totals']['mismatched'] }}</b> amount mismatch ·
                    <b class="ops-warn">{{ $s['totals']['missing_in_db'] }}</b> not in our records ·
                    <b class="ops-warn">{{ $s['totals']['missing_in_file'] }}</b> of ours missing from the file</p>
                @foreach (['mismatched' => 'Amount mismatch', 'missing_in_db' => 'In the file, not in our payments', 'missing_in_file' => 'Our payments missing from the file'] as $key => $label)
                    @if (count($s[$key]))
                        <h4 style="margin-top:.75rem;font-weight:600">{{ $label }}</h4>
                        <div style="overflow-x:auto"><table class="ops-table">
                            <thead><tr><th>Trx id</th><th>Order</th><th>File amount</th><th>Our amount</th><th>Difference</th></tr></thead>
                            <tbody>
                            @foreach ($s[$key] as $r)
                                <tr><td>{{ $r['trx_id'] }}</td><td>{{ $r['order_id'] ?? '—' }}</td><td>{{ isset($r['file_amount']) ? '৳'.number_format($r['file_amount'], 2) : '—' }}</td>
                                    <td>{{ isset($r['our_amount']) ? '৳'.number_format($r['our_amount'], 2) : '—' }}</td><td class="ops-neg">{{ isset($r['difference']) ? number_format($r['difference'], 2) : '' }}</td></tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @endif
                @endforeach
            @endif
        </div>
    @endif
</x-filament-panels::page>
