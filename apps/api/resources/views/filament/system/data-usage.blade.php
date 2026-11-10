@php($d = $this->viewData())
@php($r = $d['r'])
@php($cap = $r['capacity'])
@php($c = $d['colours'])
<x-filament-panels::page>
    <style>
        .du-wrap{display:flex;flex-direction:column;gap:1rem}
        .du-top{display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;justify-content:space-between}
        .du-top .du-select{min-width:18rem;flex:0 1 22rem}
        .du-plan{font-size:.82rem;opacity:.75;margin:0;max-width:52rem;line-height:1.45}
        .du-banner{border-radius:.75rem;padding:.75rem 1rem;font-size:.9rem;font-weight:500;border:1px solid}
        .du-banner.warning{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.45);color:#b45309}
        .du-banner.danger{background:rgba(225,29,72,.10);border-color:rgba(225,29,72,.45);color:#be123c}
        .du-banner.info{background:rgba(91,61,245,.08);border-color:rgba(91,61,245,.35)}
        .dark .du-banner.warning{color:#fbbf24}.dark .du-banner.danger{color:#fb7185}
        .du-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem}
        .du-kpi{border:1px solid rgba(127,127,127,.2);border-radius:.75rem;padding:.8rem 1rem;background:rgba(127,127,127,.03)}
        .du-kpi span{font-size:.75rem;opacity:.7;display:block}
        .du-kpi b{display:block;font-size:1.35rem;margin-top:.15rem;font-variant-numeric:tabular-nums}
        .du-kpi small{display:block;font-size:.72rem;opacity:.65;margin-top:.15rem}
        .du-pool{grid-column:1/-1;display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr) minmax(0,1fr);gap:1.25rem;align-items:center}
        @media (max-width:900px){.du-pool{grid-template-columns:1fr}}
        .du-bar{height:.75rem;border-radius:999px;background:rgba(127,127,127,.18);overflow:hidden;margin-top:.5rem;position:relative}
        .du-bar i{display:block;height:100%;border-radius:999px;background:#5b3df5}
        .du-bar i.warn{background:#f59e0b}.du-bar i.over{background:#e11d48}
        .du-bar em{position:absolute;top:-2px;bottom:-2px;width:2px;background:currentColor;opacity:.45}
        .du-card{border:1px solid rgba(127,127,127,.2);border-radius:.75rem;padding:1rem}
        .du-card h3{font-weight:600;margin:0 0 .5rem}
        .du-legend{display:flex;gap:1rem;flex-wrap:wrap;font-size:.75rem;opacity:.85;margin-bottom:.4rem}
        .du-legend span{display:inline-flex;align-items:center;gap:.35rem}
        .du-legend i{width:.7rem;height:.7rem;border-radius:2px;display:inline-block}
        .du-legend i.line{height:2px;width:1rem}
        .du-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:1rem}
        .du-chart{overflow-x:auto}.du-chart svg{min-width:560px}
        .du-table{width:100%;font-size:.8rem;border-collapse:collapse;font-variant-numeric:tabular-nums}
        .du-table th,.du-table td{padding:.35rem .5rem;border-bottom:1px solid rgba(127,127,127,.15);text-align:right;white-space:nowrap}
        .du-table th{font-weight:600;opacity:.75}
        .du-table th:first-child,.du-table td:first-child{text-align:left}
        .du-table td.du-wrapcell{white-space:normal;min-width:9rem}
        .du-table tfoot td{font-weight:600}
        .du-muted{opacity:.45}
    </style>

    <div class="du-wrap">
        <div class="du-top">
            <div class="du-select">{{ $this->form }}</div>
            <p class="du-plan">
                @if ($cap['amazon'] > 0)
                    Monthly plan: <b>{{ number_format($cap['amazon']) }}</b> Amazon India calls <b>or</b> <b>{{ number_format($cap['flipkart']) }}</b> Flipkart calls,
                    or any mix of the two. One Flipkart call uses {{ number_format($cap['weight'], 3) }} Amazon calls of the plan.
                    Only successful calls count; failed calls are never billed. Plan settings: System → Limits.
                @else
                    No monthly plan is set (System → Limits → Product data plan).
                @endif
            </p>
        </div>

        @if ($d['banner'])
            <div class="du-banner {{ $d['banner']['level'] }}" role="status">{{ $d['banner']['text'] }}</div>
        @endif

        <div class="du-kpis">
            <div class="du-kpi du-pool">
                <div>
                    <span>Plan used ({{ $r['cycle']['label'] }})</span>
                    <b>{{ number_format($r['pct'], 1) }}%</b>
                    <div class="du-bar" title="{{ number_format($r['used_units']) }} of {{ number_format($cap['amazon']) }} Amazon-call units">
                        <i class="{{ $r['pct'] >= 100 ? 'over' : ($r['pct'] >= $cap['alert'] ? 'warn' : '') }}" style="width:{{ min(100, round($r['pct'], 2)) }}%"></i>
                        <em style="left:{{ min(100, $cap['alert']) }}%" title="Alert at {{ $cap['alert'] }}%"></em>
                    </div>
                    <small>{{ number_format($r['used_units']) }} of {{ number_format($cap['amazon']) }} Amazon-call units · alert at {{ $cap['alert'] }}%</small>
                </div>
                <div>
                    <span>Remaining</span>
                    <b>≈ {{ number_format($r['remaining_amazon']) }} <small style="display:inline;font-size:.8rem">Amazon calls</small></b>
                    <small>or ≈ {{ number_format($r['remaining_flipkart']) }} Flipkart calls</small>
                </div>
                <div>
                    <span>{{ $r['over'] ? 'Cycle total' : 'Projected by end of cycle' }}</span>
                    <b>{{ number_format($r['projected_pct'], 1) }}%</b>
                    <small>
                        @if ($r['over'])
                            This cycle has ended.
                        @elseif ($r['started'])
                            ≈ {{ number_format($r['projected_units']) }} units at {{ number_format($r['per_day_units']) }} a day so far
                        @else
                            This cycle has not started yet.
                        @endif
                    </small>
                </div>
            </div>
            <div class="du-kpi"><span>Amazon India · successful</span><b>{{ number_format($r['ok']['amazon']) }}</b><small>billable calls</small></div>
            <div class="du-kpi"><span>Flipkart · successful</span><b>{{ number_format($r['ok']['flipkart']) }}</b><small>billable calls · {{ number_format($r['ok']['flipkart'] * $cap['weight']) }} Amazon-call units</small></div>
            <div class="du-kpi"><span>Failed calls</span><b>{{ number_format($r['failed_total']) }}</b><small>not billed · Amazon {{ number_format($r['failed']['amazon']) }} · Flipkart {{ number_format($r['failed']['flipkart']) }}</small></div>
            <div class="du-kpi"><span>Retries</span><b>{{ number_format($r['retries']) }}</b><small>{{ number_format($r['retries_ok']) }} succeeded (counted above), {{ number_format($r['retries'] - $r['retries_ok']) }} failed</small></div>
            @if ($r['ok']['other'] > 0 || $r['failed']['other'] > 0)
                <div class="du-kpi"><span>Other stores</span><b>{{ number_format($r['ok']['other']) }}</b><small>successful calls outside the plan (Myntra)</small></div>
            @endif
        </div>

        <div class="du-card">
            <h3>Calls per day</h3>
            <div class="du-legend">
                <span><i style="background:{{ $c['amazon'] }}"></i>Amazon India · successful</span>
                <span><i style="background:{{ $c['flipkart'] }}"></i>Flipkart · successful</span>
                <span><i class="line" style="background:{{ $c['failed'] }}"></i>Failed (not billed)</span>
            </div>
            <div class="du-chart">{{ $d['chart'] }}</div>
        </div>

        <div class="du-grid">
            <div class="du-card">
                <h3>By purpose</h3>
                <div style="overflow-x:auto">
                    <table class="du-table">
                        <thead><tr><th>Purpose</th><th>Amazon India</th><th>Flipkart</th><th>Plan units</th><th>Failed</th><th>Retries</th></tr></thead>
                        <tbody>
                        @forelse ($r['purposes'] as $p)
                            <tr>
                                <td class="du-wrapcell">{{ $p['label'] }}</td>
                                <td>{{ number_format($p['amazon']) }}</td>
                                <td>{{ number_format($p['flipkart']) }}</td>
                                <td>{{ number_format($p['units']) }}</td>
                                <td class="{{ $p['failed'] ? '' : 'du-muted' }}">{{ number_format($p['failed']) }}</td>
                                <td class="{{ $p['retries'] ? '' : 'du-muted' }}">{{ number_format($p['retries']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center;opacity:.6">No calls in this cycle.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="du-card">
                <h3>By day</h3>
                <div style="overflow-x:auto;max-height:28rem;overflow-y:auto">
                    <table class="du-table">
                        <thead><tr><th>Date</th><th>Amazon India</th><th>Flipkart</th><th>Plan units</th><th>Failed</th><th>Retries</th></tr></thead>
                        <tbody>
                        @foreach (array_reverse($r['days'], true) as $day => $v)
                            @continue($day > now(\App\Support\BizTime::TZ)->toDateString())
                            @php($empty = $v['amazon'] + $v['flipkart'] + $v['failed'] === 0)
                            <tr class="{{ $empty ? 'du-muted' : '' }}">
                                <td>{{ \Illuminate\Support\Carbon::parse($day)->format('D j M') }}</td>
                                <td>{{ number_format($v['amazon']) }}</td>
                                <td>{{ number_format($v['flipkart']) }}</td>
                                <td>{{ number_format($v['units']) }}</td>
                                <td>{{ number_format($v['failed']) }}</td>
                                <td>{{ number_format($v['retries']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td>{{ number_format($r['ok']['amazon']) }}</td>
                                <td>{{ number_format($r['ok']['flipkart']) }}</td>
                                <td>{{ number_format($r['used_units']) }}</td>
                                <td>{{ number_format($r['failed_total']) }}</td>
                                <td>{{ number_format($r['retries']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
