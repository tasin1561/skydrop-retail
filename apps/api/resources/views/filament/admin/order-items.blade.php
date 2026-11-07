@php
    use App\Filament\Support\Admin;
    $groups = $record->items->groupBy('market');
@endphp
<div style="display:grid;gap:1rem">
    @foreach ($groups as $market => $items)
        <div style="border:1px solid rgba(127,127,127,.25);border-radius:.75rem;overflow:hidden">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem .9rem;background:rgba(11,107,203,.08)">
                <strong style="color:#0b6bcb">{{ Admin::marketLabel($market) }}</strong>
                <span style="font-size:.8rem;opacity:.7">{{ $items->sum('qty') }} item(s) · ₹{{ number_format($items->sum(fn ($i) => $i->unit_inr * $i->qty)) }} · {{ Admin::bdt($items->sum(fn ($i) => $i->unit_bdt * $i->qty)) }}</span>
            </div>
            @foreach ($items as $i)
                <div style="display:flex;gap:.9rem;padding:.7rem .9rem;border-top:1px solid rgba(127,127,127,.15);align-items:center">
                    @if ($i->image)
                        <img src="{{ $i->image }}" alt="" loading="lazy" style="width:56px;height:56px;object-fit:contain;background:#fff;border-radius:.4rem;flex:none">
                    @else
                        <div style="width:56px;height:56px;border-radius:.4rem;background:rgba(127,127,127,.15);flex:none"></div>
                    @endif
                    <div style="flex:1;min-width:0">
                        <a href="{{ Admin::sourceUrl($i->market, $i->product_id) }}" target="_blank" rel="noopener" style="font-weight:600;color:inherit;text-decoration:underline;text-decoration-color:rgba(11,107,203,.5)">{{ $i->title }} ↗</a>
                        <div style="font-size:.8rem;opacity:.7;margin-top:.15rem">
                            {{ $i->product_id }}@if ($i->variant) · {{ $i->variant }}@endif · {{ rtrim(rtrim(number_format($i->weight_kg, 3), '0'), '.') }} kg
                        </div>
                        <div style="font-size:.8rem;margin-top:.2rem">
                            @if ($i->purchase_ref)
                                <span style="color:#059669">Bought · ref {{ $i->purchase_ref }}</span>
                            @else
                                <span style="color:#d97706">Not bought yet</span>
                            @endif
                        </div>
                    </div>
                    <div style="text-align:right;white-space:nowrap">
                        <div style="font-weight:600">{{ $i->qty }} × {{ Admin::bdt($i->unit_bdt) }}</div>
                        <div style="font-size:.8rem;opacity:.7">₹{{ number_format($i->unit_inr) }} each</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
