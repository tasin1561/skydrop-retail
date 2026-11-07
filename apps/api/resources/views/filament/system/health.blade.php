<x-filament-panels::page>
    @php
        $report = $this->getReport();
        $color = ['green' => 'success', 'amber' => 'warning', 'red' => 'danger'];
        $dot = ['green' => '#10b981', 'amber' => '#f59e0b', 'red' => '#ef4444'];
        $label = ['green' => 'OK', 'amber' => 'Attention', 'red' => 'Problem'];
    @endphp
    <style>
        .sh-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 22rem), 1fr)); }
        .sh-card { border-radius: .75rem; border: 1px solid rgba(127,127,127,.22); padding: 1rem; display: flex; flex-direction: column; gap: .6rem; border-top-width: 4px; }
        .sh-head { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
        .sh-head h3 { margin: 0; font-weight: 600; font-size: 1rem; }
        .sh-sum { font-size: .82rem; opacity: .7; }
        .sh-row { display: flex; justify-content: space-between; gap: .75rem; font-size: .84rem; padding: .2rem 0; border-top: 1px dashed rgba(127,127,127,.18); }
        .sh-row span:first-child { opacity: .75; }
        .sh-row span:last-child { text-align: right; font-variant-numeric: tabular-nums; word-break: break-word; }
        .sh-wide { grid-column: 1 / -1; }
        .sh-table { width: 100%; font-size: .8rem; border-collapse: collapse; }
        .sh-table th, .sh-table td { text-align: left; padding: .35rem .4rem; border-top: 1px solid rgba(127,127,127,.15); vertical-align: top; }
        .sh-table th { font-weight: 600; opacity: .7; }
        .sh-mono { font-family: ui-monospace, monospace; font-size: .75rem; }
        .sh-scroll { overflow-x: auto; }
        .sh-pill { display: inline-block; width: .6rem; height: .6rem; border-radius: 999px; margin-right: .35rem; }
    </style>

    <div wire:poll.30s style="display:flex;flex-direction:column;gap:1rem">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
            <div style="display:flex;gap:.5rem;align-items:center">
                <x-filament::badge :color="$color[$report['status']]" size="lg">Overall: {{ $label[$report['status']] }}</x-filament::badge>
                <span style="font-size:.8rem;opacity:.65">Updated {{ \Illuminate\Support\Carbon::parse($report['generatedAt'])->format('H:i:s') }} · refreshes every 30 s</span>
            </div>
            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="$refresh">Refresh now</x-filament::button>
        </div>

        <div class="sh-grid">
            @foreach ($report['cards'] as $key => $card)
                @continue(in_array($key, ['errors'], true))
                <section class="sh-card" style="border-top-color: {{ $dot[$card['status']] }}" wire:key="card-{{ $key }}">
                    <div class="sh-head">
                        <h3><span class="sh-pill" style="background: {{ $dot[$card['status']] }}"></span>{{ $card['title'] }}</h3>
                        <x-filament::badge :color="$color[$card['status']]" size="sm">{{ $label[$card['status']] }}</x-filament::badge>
                    </div>
                    <div class="sh-sum">{{ $card['summary'] }}</div>
                    <div>
                        @foreach ($card['items'] as $item)
                            <div class="sh-row">
                                <span>{{ $item['label'] }}</span>
                                <span @if ($item['status']) style="color: {{ $dot[$item['status']] }};font-weight:600" @endif>{{ is_scalar($item['value']) ? $item['value'] : json_encode($item['value']) }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($key === 'database' && ! empty($card['tables']))
                        <details>
                            <summary style="font-size:.8rem;cursor:pointer">Largest tables</summary>
                            <table class="sh-table">
                                @foreach ($card['tables'] as $t)
                                    <tr><td class="sh-mono">{{ $t['name'] }}</td><td style="text-align:right">{{ $t['size'] ? \App\Services\System\HealthService::bytes($t['size']) : (isset($t['rows']) ? number_format((int) $t['rows']).' rows' : '—') }}</td></tr>
                                @endforeach
                            </table>
                        </details>
                    @endif
                    @if ($key === 'queue' && ! empty($card['help']))
                        <div style="font-size:.75rem;opacity:.65">{{ $card['help'] }}</div>
                    @endif
                    @if ($key === 'messaging')
                        <div><x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-path" wire:click="refreshSms">Refresh SMS balance</x-filament::button></div>
                    @endif
                    @if ($key === 'features' && ! empty($card['link']))
                        <div><x-filament::link :href="$card['link']" size="sm" icon="heroicon-o-adjustments-vertical">Open Features</x-filament::link></div>
                    @endif
                </section>
            @endforeach

            @php $q = $report['cards']['queue'] ?? []; @endphp
            @if (! empty($q['failed']))
                <section class="sh-card sh-wide" style="border-top-color: {{ $dot['amber'] }}">
                    <div class="sh-head"><h3>Recent failed jobs</h3></div>
                    <div class="sh-scroll">
                        <table class="sh-table">
                            <tr><th>Job</th><th>Queue</th><th>Error</th><th>Failed at</th><th></th></tr>
                            @foreach ($q['failed'] as $f)
                                <tr wire:key="failed-{{ $f['uuid'] }}">
                                    <td>{{ $f['job'] }}</td><td>{{ $f['queue'] }}</td><td class="sh-mono">{{ $f['error'] }}</td><td>{{ $f['failedAt'] }}</td>
                                    <td style="white-space:nowrap">
                                        <x-filament::button size="xs" wire:click="retryJob('{{ $f['uuid'] }}')">Retry</x-filament::button>
                                        <x-filament::button size="xs" color="danger" wire:click="forgetJob('{{ $f['uuid'] }}')" wire:confirm="Delete this failed job?">Forget</x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </section>
            @endif

            @php $p = $report['cards']['providers'] ?? []; @endphp
            @if (! empty($p['circuits']))
                <section class="sh-card sh-wide" style="border-top-color: {{ $dot[$p['status']] }}">
                    <div class="sh-head"><h3>Provider circuits</h3><span class="sh-sum">success rate and latency from api_logs</span></div>
                    <div class="sh-scroll">
                        <table class="sh-table">
                            <tr><th>Provider : store</th><th>Circuit</th><th>1h calls · ok · p50 / p95</th><th>24h calls · ok · p50 / p95</th><th></th></tr>
                            @foreach ($p['circuits'] as $k => $c)
                                @php $h = $c['stats']['hour'] ?? null; $d = $c['stats']['day'] ?? null; @endphp
                                <tr wire:key="circuit-{{ $k }}">
                                    <td class="sh-mono">{{ $k }}</td>
                                    <td><x-filament::badge :color="$color[$c['status']]" size="sm">{{ str_replace('_', '-', $c['state']) }}</x-filament::badge>
                                        @if ($c['openedAt']) <div style="font-size:.72rem;opacity:.6">opened {{ \Illuminate\Support\Carbon::parse($c['openedAt'])->diffForHumans() }}</div> @endif</td>
                                    <td>{{ $h ? $h['calls'].' · '.($h['successPct'] ?? '—').'% · '.($h['p50'] ?? '—').' / '.($h['p95'] ?? '—').' ms' : '—' }}</td>
                                    <td>{{ $d ? $d['calls'].' · '.($d['successPct'] ?? '—').'% · '.($d['p50'] ?? '—').' / '.($d['p95'] ?? '—').' ms' : '—' }}</td>
                                    <td>@if ($c['state'] !== 'closed')<x-filament::button size="xs" color="gray" wire:click="resetCircuit('{{ $k }}')">Reset</x-filament::button>@endif</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </section>
            @endif

            @php $e = $report['cards']['errors'] ?? null; @endphp
            @if ($e)
                <section class="sh-card sh-wide" style="border-top-color: {{ $dot[$e['status']] }}">
                    <div class="sh-head">
                        <h3><span class="sh-pill" style="background: {{ $dot[$e['status']] }}"></span>Errors <span class="sh-sum">· {{ $e['summary'] }}</span></h3>
                        @if (! empty($e['events']))
                            <x-filament::button size="xs" color="gray" wire:click="clearErrors" wire:confirm="Clear all grouped errors?">Clear</x-filament::button>
                        @endif
                    </div>
                    @if (empty($e['events']))
                        <div class="sh-sum">No exceptions recorded.</div>
                    @else
                        <div class="sh-scroll">
                            <table class="sh-table">
                                <tr><th>Count</th><th>Exception</th><th>Where</th><th>Last seen</th></tr>
                                @foreach ($e['events'] as $ev)
                                    <tr wire:key="err-{{ $ev['id'] }}">
                                        <td>{{ $ev['count'] }}</td>
                                        <td><strong>{{ class_basename($ev['class']) }}</strong><div class="sh-mono">{{ $ev['message'] }}</div><div style="font-size:.72rem;opacity:.6">{{ $ev['url'] }}</div></td>
                                        <td class="sh-mono">{{ $ev['where'] }}</td>
                                        <td style="white-space:nowrap">{{ $ev['lastSeen'] ? \Illuminate\Support\Carbon::parse($ev['lastSeen'])->diffForHumans() : '' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
