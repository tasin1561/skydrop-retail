@php($alerts = \App\Support\Features::enabled('supplier_price_alerts') ? app(\App\Services\Fulfilment\PriceAlertService::class)->openAlerts() : collect())
@if ($alerts->isNotEmpty())
    <div role="alert" style="border:1px solid rgb(244,63,94);background:rgba(244,63,94,.08);color:rgb(190,18,60);border-radius:.75rem;padding:.75rem 1rem;display:flex;gap:.75rem;align-items:flex-start;flex-wrap:wrap">
        <strong style="flex:none">{{ $alerts->count() }} price alert(s)</strong>
        <span style="flex:1;min-width:14rem;color:inherit">
            @foreach ($alerts->take(4) as $i)
                <span style="display:block">{{ $i->order_id }} · {{ \Illuminate\Support\Str::limit($i->title, 50) }}:
                    @if (($i->price_alert['inStock'] ?? true) === false) out of stock
                    @else ₹{{ number_format($i->price_alert['oldInr']) }} → ₹{{ number_format($i->price_alert['newInr']) }} (+{{ $i->price_alert['risePct'] }}%), margin {{ \App\Filament\Support\Admin::bdt($i->price_alert['marginBeforeBdt']) }} → {{ \App\Filament\Support\Admin::bdt($i->price_alert['marginAfterBdt']) }}
                    @endif
                </span>
            @endforeach
        </span>
        <a href="{{ \App\Filament\Pages\PriceAlerts::getUrl() }}" style="font-weight:700;text-decoration:underline;min-height:44px;display:inline-flex;align-items:center">Review before buying →</a>
    </div>
@endif
