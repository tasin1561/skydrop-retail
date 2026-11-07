@php
    $order = $getRecord();
    $items = $order->items()->get();
    $alerts = $items->filter(fn ($i) => $i->price_alert && empty($i->price_alert['resolution']) && ! $i->cancelled_at);
    $receipts = \App\Models\Fulfilment\HubReceipt::query()->where('order_id', $order->id)->orderBy('id')->get()->groupBy('order_item_id');
    $returns = \App\Models\Fulfilment\ReturnRequest::query()->where('order_id', $order->id)->latest('id')->get();
    $box = \App\Models\Fulfilment\HubBox::query()->where('order_id', $order->id)->latest('id')->first();
    $flag = $order->weight_flag;
    $bdt = fn ($v) => \App\Filament\Support\Admin::bdt($v);
@endphp
<div style="display:grid;gap:.75rem;font-size:.875rem">
    @if ($alerts->isNotEmpty())
        <div role="alert" style="border:1px solid rgb(244,63,94);background:rgba(244,63,94,.08);color:rgb(190,18,60);border-radius:.75rem;padding:.75rem 1rem">
            <strong>Supplier price alert</strong>
            @foreach ($alerts as $i)
                <div>{{ \Illuminate\Support\Str::limit($i->title, 60) }}:
                    @if (($i->price_alert['inStock'] ?? true) === false) out of stock at the source.
                    @else ₹{{ number_format($i->price_alert['oldInr']) }} → ₹{{ number_format($i->price_alert['newInr']) }} (+{{ $i->price_alert['risePct'] }}%). Margin {{ $bdt($i->price_alert['marginBeforeBdt']) }} → {{ $bdt($i->price_alert['marginAfterBdt']) }}.
                    @endif
                </div>
            @endforeach
            @if (\App\Filament\Pages\PriceAlerts::canAccess())<a href="{{ \App\Filament\Pages\PriceAlerts::getUrl() }}" style="font-weight:700;text-decoration:underline">Absorb, ask the customer, switch store or cancel →</a>@endif
        </div>
    @endif

    @if ($flag)
        <div style="border:1px solid rgba(245,158,11,.6);background:rgba(245,158,11,.1);border-radius:.75rem;padding:.6rem .9rem">
            <strong>Weight:</strong> measured {{ $flag['actualKg'] }} kg vs {{ $flag['estimateKg'] }} kg estimated (+{{ $flag['variancePct'] }}%), extra shipping ≈ {{ $bdt($flag['extraBdt']) }}.
            {{ empty($flag['resolution']) ? 'Resolve it on Hub → Pack & label.' : 'Resolved: '.($flag['resolution'] === 'charge' ? 'charged to the customer' : 'absorbed').'.' }}
        </div>
    @endif

    <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:.5rem">
        <div><dt style="opacity:.65">Unboxing</dt><dd style="font-weight:600">{{ $order->unboxing ? ($order->unboxing_status ? str_replace('_', ' ', $order->unboxing_status) : 'paid, not yet unboxed').' · '.$bdt($order->unboxing_fee_bdt) : 'no' }}</dd></div>
        <div><dt style="opacity:.65">Actual weight</dt><dd style="font-weight:600">{{ $order->actual_weight_kg ? number_format($order->actual_weight_kg, 2).' kg' : '—' }}</dd></div>
        <div><dt style="opacity:.65">Box</dt><dd style="font-weight:600">{{ $box?->code ?? '—' }}</dd></div>
        @if ($order->replacement_of)<div><dt style="opacity:.65">Replacement for</dt><dd style="font-weight:600">{{ $order->replacement_of }}</dd></div>@endif
    </dl>

    @if ($receipts->isNotEmpty())
        <div>
            <strong>Received at the Kolkata hub</strong>
            <ul style="margin:.35rem 0 0;padding:0;list-style:none;display:grid;gap:.4rem">
                @foreach ($items as $it)
                    @php($r = $receipts[$it->id] ?? collect())
                    @php($last = $r->last())
                    <li style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                        <span style="min-width:0;flex:1">IT-{{ $it->id }} · {{ \Illuminate\Support\Str::limit($it->title, 50) }}{{ $it->cancelled_at ? ' (cancelled)' : '' }}</span>
                        @if ($last)
                            <span>{{ number_format($last->weight_kg, 2) }} kg{{ $last->dims() ? ' · '.$last->dims() : '' }}</span>
                            <span style="font-weight:700;color:{{ $last->condition === 'ok' ? 'rgb(5,150,105)' : 'rgb(225,29,72)' }}">{{ \App\Models\Fulfilment\HubReceipt::CONDITIONS[$last->condition] }}</span>
                            @foreach ($last->photoUrls() as $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="Hub photo" style="height:40px;width:40px;object-fit:cover;border-radius:.35rem"></a>
                            @endforeach
                        @else
                            <span style="opacity:.6">{{ $it->cancelled_at ? '' : 'not yet received' }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($returns->isNotEmpty())
        <div>
            <strong>Returns</strong>
            @foreach ($returns as $r)
                <div>@if (\App\Filament\Resources\ReturnRequests\ReturnRequestResource::canView($r))<a href="{{ \App\Filament\Resources\ReturnRequests\ReturnRequestResource::getUrl('view', ['record' => $r]) }}" style="text-decoration:underline">#{{ $r->id }}</a>@else #{{ $r->id }}@endif
                    · {{ \App\Models\Fulfilment\ReturnRequest::REASONS[$r->reason] ?? $r->reason }} · {{ \App\Models\Fulfilment\ReturnRequest::STATUSES[$r->status] ?? $r->status }}</div>
            @endforeach
        </div>
    @endif
</div>
