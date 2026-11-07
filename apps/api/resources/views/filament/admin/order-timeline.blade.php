@php
    use App\Filament\Support\Admin;
    $events = $record->events;
    $colors = ['gray' => '#64748b', 'info' => '#0284c7', 'primary' => '#0b6bcb', 'warning' => '#d97706', 'success' => '#059669', 'danger' => '#e11d48'];
@endphp
<ol style="list-style:none;margin:0;padding:0;display:grid;gap:.1rem">
    @forelse ($events as $e)
        <li style="display:flex;gap:.75rem">
            <div style="display:flex;flex-direction:column;align-items:center">
                <span style="width:.75rem;height:.75rem;border-radius:999px;margin-top:.3rem;background:{{ $colors[Admin::ORDER_STATUS_COLORS[$e->status] ?? 'gray'] }}"></span>
                @unless ($loop->last)<span style="flex:1;width:2px;background:rgba(127,127,127,.25)"></span>@endunless
            </div>
            <div style="padding-bottom:.8rem">
                <div style="font-weight:600">{{ Admin::ORDER_STATUS_LABELS[$e->status] ?? $e->status }}
                    <span style="font-size:.7rem;font-weight:500;padding:.05rem .4rem;border-radius:999px;background:rgba(127,127,127,.15);margin-left:.25rem">{{ $e->source }}</span>
                </div>
                <div style="font-size:.8rem;opacity:.7">{{ \Illuminate\Support\Carbon::parse($e->at)->timezone('Asia/Dhaka')->format('d M Y, H:i') }} (Dhaka)</div>
                @if ($e->note)<div style="font-size:.85rem;margin-top:.15rem">{{ $e->note }}</div>@endif
            </div>
        </li>
    @empty
        <li style="opacity:.6">No events yet.</li>
    @endforelse
</ol>
