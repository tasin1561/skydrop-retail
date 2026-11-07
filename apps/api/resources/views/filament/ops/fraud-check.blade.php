<x-filament-panels::page>
    <form wire:submit="check" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
        <div style="flex:1;min-width:220px">{{ $this->form }}</div>
        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">Check</x-filament::button>
    </form>

    @if ($result)
        @php($color = ['high' => '#e11d48', 'medium' => '#d97706', 'low' => '#059669'][$result['level']])
        <div style="border:1px solid rgba(127,127,127,.2);border-radius:.75rem;padding:1rem;display:grid;gap:1rem">
            <div style="display:flex;align-items:center;gap:1rem">
                <div style="font-size:2.25rem;font-weight:800;color:{{ $color }}">{{ $result['score'] }}</div>
                <div><b style="color:{{ $color }};text-transform:uppercase">{{ $result['level'] }} risk</b><br>
                    <small style="opacity:.7">{{ $phone }} · courier success {{ $result['courier_success'] !== null ? round($result['courier_success'] * 100).'%' : 'no history' }} over {{ $result['courier_total'] }} parcel(s)</small></div>
            </div>
            <div>
                <h3 style="font-weight:600">Reasons</h3>
                <ul style="list-style:disc;padding-left:1.25rem;font-size:.9rem">
                    @forelse ($result['reasons'] as $r)<li>{{ $r }}</li>@empty<li style="opacity:.6">No risk signals.</li>@endforelse
                </ul>
            </div>
            <div>
                <h3 style="font-weight:600">Couriers</h3>
                <table style="width:100%;font-size:.85rem">
                    <thead><tr style="text-align:left"><th>Courier</th><th>Total</th><th>Delivered</th><th>Cancelled</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($result['couriers'] as $id => $c)
                        <tr><td>{{ ucfirst($id) }}</td><td>{{ $c['total'] }}</td><td>{{ $c['delivered'] }}</td><td>{{ $c['cancelled'] }}</td>
                            <td style="opacity:.7">{{ isset($c['error']) ? 'error: '.$c['error'] : ($c['cached'] ? 'cached' : 'live') }}</td></tr>
                    @empty
                        <tr><td colspan="5" style="opacity:.6">No courier accounts configured (set STEADFAST_API_KEY / STEADFAST_SECRET_KEY).</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
