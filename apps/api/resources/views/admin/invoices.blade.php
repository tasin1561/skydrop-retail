@php use App\Filament\Support\Admin; @endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoices · Skydrop Retail</title>
<style>
  :root { --accent:#5b3df5; --coral:#ff4d2e; --ink:#0b0b0f; --muted:#55555f; --line:#e7e5de; }
  * { box-sizing:border-box; }
  body { font:14px/1.45 "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif; color:var(--ink); margin:0; background:#f7f7f4; }
  .inv { background:#fff; max-width:800px; margin:24px auto; padding:40px; border-radius:20px; page-break-after:always; }
  .inv:last-child { page-break-after:auto; }
  header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid var(--accent); padding-bottom:16px; }
  .brand { display:flex; gap:10px; align-items:center; font-weight:700; font-size:20px; }
  .brand img { width:40px; height:40px; }
  .muted { color:var(--muted); }
  h2 { margin:0 0 4px; font-size:22px; }
  .grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin:20px 0; }
  table { width:100%; border-collapse:collapse; }
  th, td { text-align:left; padding:8px 6px; border-bottom:1px solid var(--line); vertical-align:top; }
  th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); }
  td.r, th.r { text-align:right; }
  .group td { background:#f7f7f4; font-weight:600; color:var(--accent); }
  .totals { margin-left:auto; width:300px; margin-top:16px; }
  .totals td { border:0; padding:4px 6px; }
  .totals .grand td { font-size:18px; font-weight:700; border-top:2px solid var(--ink); padding-top:8px; }
  footer { margin-top:28px; font-size:12px; color:var(--muted); border-top:1px solid var(--line); padding-top:12px; }
  .noprint { text-align:center; margin:16px; }
  .noprint button { background:var(--accent); color:#fff; border:0; padding:10px 20px; border-radius:999px; font-size:14px; cursor:pointer; }
  @media print { body { background:#fff; } .inv { margin:0; border-radius:0; padding:24px; } .noprint { display:none; } }
</style>
</head>
<body>
<div class="noprint"><button onclick="window.print()">Print {{ $orders->count() }} invoice{{ $orders->count() > 1 ? 's' : '' }}</button></div>
@foreach ($orders as $o)
  @php $addr = $o->address ?? []; @endphp
  <section class="inv">
    <header>
      <div class="brand"><img src="{{ asset('images/skydrop-retail-logo.svg') }}" alt=""> <span>Skydrop <span style="color:var(--coral)">Retail</span></span></div>
      <div style="text-align:right">
        <h2>Invoice {{ $o->id }}</h2>
        <div class="muted">{{ $o->created_at?->format('d M Y, H:i') }}</div>
        @if ($o->tracking_code)<div>Tracking: <strong>{{ $o->tracking_code }}</strong></div>@endif
      </div>
    </header>
    <div class="grid">
      <div>
        <div class="muted">Deliver to</div>
        <strong>{{ $addr['name'] ?? $o->user?->name }}</strong><br>
        {{ $addr['phone'] ?? $o->user?->phone }}<br>
        {{ $addr['line'] ?? '' }}<br>
        {{ collect([$addr['area'] ?? null, $addr['district'] ?? null, $addr['division'] ?? null])->filter()->implode(', ') }}
      </div>
      <div>
        <div class="muted">Payment</div>
        <strong>{{ method_exists($o, 'paymentLabel') ? $o->paymentLabel() : $o->payment_method }}</strong> · {{ ucfirst($o->payment_status) }}<br>
        Paid {{ Admin::bdt($o->paid_bdt) }}@if ($o->total_bdt - $o->paid_bdt > 0) · Due on delivery {{ Admin::bdt($o->total_bdt - $o->paid_bdt) }}@endif
        <div class="muted" style="margin-top:8px">Status: {{ Admin::ORDER_STATUS_LABELS[$o->status] ?? $o->status }}</div>
      </div>
    </div>
    <table>
      <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Amount</th></tr></thead>
      <tbody>
      @foreach ($o->items->groupBy('market') as $market => $items)
        <tr class="group"><td colspan="4">Bought from {{ Admin::marketLabel($market) }}</td></tr>
        @foreach ($items as $i)
          <tr>
            <td>{{ $i->title }}@if ($i->variant)<div class="muted">{{ $i->variant }}</div>@endif</td>
            <td class="r">{{ $i->qty }}</td>
            <td class="r">{{ Admin::bdt($i->unit_bdt) }}</td>
            <td class="r">{{ Admin::bdt($i->unit_bdt * $i->qty) }}</td>
          </tr>
        @endforeach
      @endforeach
      </tbody>
    </table>
    <table class="totals">
      <tr><td>Subtotal</td><td class="r">{{ Admin::bdt($o->subtotal_bdt) }}</td></tr>
      <tr><td>Shipping (India → Bangladesh)</td><td class="r">{{ Admin::bdt($o->shipping_bdt) }}</td></tr>
      @if ($o->discount_bdt > 0)<tr><td>Discount{{ $o->coupon_code ? ' ('.$o->coupon_code.')' : '' }}</td><td class="r">−{{ Admin::bdt($o->discount_bdt) }}</td></tr>@endif
      <tr class="grand"><td>Total</td><td class="r">{{ Admin::bdt($o->total_bdt) }}</td></tr>
    </table>
    <footer>Prices include import duties and our service fee. Delivered by Skydrop from our Kolkata hub. Thank you for shopping with Skydrop Retail.</footer>
  </section>
@endforeach
</body>
</html>
