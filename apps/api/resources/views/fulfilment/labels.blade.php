<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Box labels</title>
<style>
    @page { size: 100mm 150mm; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #000; background: #f0efe9; }
    .label { width: 100mm; height: 150mm; padding: 6mm; background: #fff; margin: 8mm auto; display: flex; flex-direction: column; gap: 3mm; page-break-after: always; border: 1px solid #000; }
    .row { display: flex; justify-content: space-between; align-items: baseline; gap: 2mm; }
    .brand { font-weight: 800; font-size: 13pt; }
    .route { font-size: 10pt; border: 1.5px solid #000; padding: 1.5mm 2mm; font-weight: 700; text-align: center; }
    .big { font-size: 26pt; font-weight: 800; letter-spacing: .02em; }
    .city { font-size: 20pt; font-weight: 800; text-transform: uppercase; }
    .k { font-size: 8pt; text-transform: uppercase; letter-spacing: .06em; }
    .v { font-size: 14pt; font-weight: 700; }
    .bar svg { width: 100%; height: auto; display: block; }
    .grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2mm; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; padding: 2mm 0; }
    .tools { text-align: center; margin: 4mm; }
    .tools button { min-height: 44px; padding: 0 16px; font-size: 14px; }
    @media print { body { background: #fff; } .label { margin: 0; border: 0; } .tools { display: none; } }
</style>
</head>
<body>
<div class="tools"><button type="button" onclick="window.print()">Print {{ $boxes->count() }} label(s)</button></div>
@forelse ($boxes as $box)
    @php($o = $orders[$box->order_id] ?? null)
    <section class="label" aria-label="Label {{ $box->code }}">
        <div class="row"><span class="brand">Skydrop Retail</span><span class="k">{{ $box->packed_at?->timezone('Asia/Kolkata')->format('d M Y H:i') }}</span></div>
        <div class="route">KOLKATA HUB {{ config('fulfilment.hub.pincode') }} → BENAPOLE → DHAKA</div>
        <div><div class="k">Order</div><div class="big">{{ $box->order_id }}</div></div>
        <div class="bar">{!! \App\Support\Code128::svg($box->order_id, 70, 2.2) !!}</div>
        <div class="grid">
            <div><div class="k">Weight</div><div class="v">{{ number_format($box->weight_kg, 2) }} kg</div></div>
            <div><div class="k">Items</div><div class="v">{{ $box->item_count }}</div></div>
            <div><div class="k">Box</div><div class="v" style="font-size:10pt">{{ $box->code }}</div></div>
        </div>
        <div><div class="k">Deliver to</div><div class="city">{{ $o?->address['district'] ?? '' }}</div>
            <div style="font-size:10pt">{{ $o?->address['area'] ?? '' }}{{ isset($o?->address['division']) ? ', '.$o->address['division'] : '' }}</div></div>
        @if ($o?->tracking_code)
            <div class="bar" style="margin-top:auto">{!! \App\Support\Code128::svg($o->tracking_code, 42, 1.8) !!}</div>
        @endif
    </section>
@empty
    <p style="text-align:center;padding:2rem">No packed boxes for these orders yet. Use “Pack box” first.</p>
@endforelse
<script>if (location.search.indexOf('print=0') === -1 && document.querySelector('.label')) setTimeout(function () { window.print(); }, 300);</script>
</body>
</html>
