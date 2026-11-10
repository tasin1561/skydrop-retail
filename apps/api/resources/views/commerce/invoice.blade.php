@php
    $bdt = fn ($n) => '৳' . number_format(round((float) $n));
    $a = $order->address ?? [];
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice {{ $order->id }} · Skydrop Retail</title>
<style>
  :root { --ink:#0b0b0f; --ink2:#55555f; --line:#e7e5de; --accent:#5b3df5; --coral:#ff4d2e; --bg:#ffffff; --soft:#f7f7f4; }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--soft); color:var(--ink); font:14px/1.5 "Plus Jakarta Sans", -apple-system, "Segoe UI", Roboto, "Noto Sans Bengali", sans-serif; }
  .page { max-width:820px; margin:24px auto; background:var(--bg); padding:40px; border-radius:20px; box-shadow:0 1px 1px rgba(0,0,0,.04), 0 6px 20px rgba(20,16,60,.06); }
  header { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; border-bottom:3px solid var(--accent); padding-bottom:20px; }
  .brand { font-size:24px; font-weight:800; letter-spacing:-.02em; }
  .brand span { color:var(--coral); }
  .muted { color:var(--ink2); }
  h2 { font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:var(--ink2); margin:0 0 6px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:24px; margin:24px 0; }
  table { width:100%; border-collapse:collapse; }
  th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); vertical-align:top; }
  th { font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:var(--ink2); }
  td.num, th.num { text-align:right; white-space:nowrap; }
  .totals { margin-left:auto; max-width:320px; margin-top:16px; }
  .totals div { display:flex; justify-content:space-between; padding:4px 0; }
  .totals .grand { border-top:2px solid var(--ink); margin-top:6px; padding-top:8px; font-weight:800; font-size:16px; }
  .pill { display:inline-block; padding:2px 10px; border-radius:999px; background:var(--soft); font-weight:700; font-size:12px; }
  footer { margin-top:32px; font-size:12px; color:var(--ink2); border-top:1px solid var(--line); padding-top:16px; }
  .print { position:fixed; right:16px; bottom:16px; background:var(--accent); color:#fff; border:0; border-radius:999px; padding:10px 18px; font-weight:700; cursor:pointer; }
  @media (max-width:600px) { .page { margin:0; border-radius:0; padding:20px 16px; } }
  @media print { body { background:#fff; } .page { box-shadow:none; margin:0; max-width:none; } .print { display:none; } }
</style>
</head>
<body>
<main class="page">
  <header>
    <div>
      <div class="brand">Skydrop <span>Retail</span></div>
      <div class="muted">Indian stores, delivered to your door in Bangladesh</div>
    </div>
    <div style="text-align:right">
      <div style="font-size:20px;font-weight:800">INVOICE</div>
      <div><strong>{{ $order->id }}</strong></div>
      <div class="muted">{{ $order->created_at?->timezone('Asia/Dhaka')->format('j M Y, g:i A') }}</div>
      <div class="pill">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</div>
    </div>
  </header>

  <section class="grid">
    <div>
      <h2>Deliver to</h2>
      <strong>{{ $a['name'] ?? '' }}</strong><br>
      {{ $a['phone'] ?? '' }}<br>
      {{ $a['line'] ?? '' }}, {{ $a['area'] ?? '' }}<br>
      {{ $a['district'] ?? '' }}, {{ $a['division'] ?? '' }}
    </div>
    <div>
      <h2>Payment</h2>
      {{ $order->paymentLabel() }}<br>
      Status: <strong>{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</strong><br>
      Paid: {{ $bdt($order->paid_bdt) }} · Due on delivery: {{ $bdt($order->balanceBdt()) }}
    </div>
    <div>
      <h2>Skydrop tracking</h2>
      <strong>{{ $order->tracking_code }}</strong><br>
      <span class="muted">Kolkata hub → Dhaka → your door</span>
    </div>
  </section>

  <table>
    <thead><tr><th>Item</th><th>Store</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Amount</th></tr></thead>
    <tbody>
    @foreach ($order->items as $item)
      <tr>
        <td>{{ $item->title }}@if ($item->variant)<br><span class="muted">{{ $item->variant }}</span>@endif</td>
        <td>{{ ['amazon' => 'amazon.in', 'flipkart' => 'flipkart.com', 'bigbasket' => 'bigbasket.com', 'myntra' => 'myntra.com'][$item->market] ?? $item->market }}</td>
        <td class="num">{{ $item->qty }}</td>
        <td class="num">{{ $bdt($item->unit_bdt) }}</td>
        <td class="num">{{ $bdt($item->unit_bdt * $item->qty) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>

  <div class="totals">
    <div><span>Items</span><span>{{ $bdt($order->subtotal_bdt) }}</span></div>
    <div><span>Skydrop shipping</span><span>{{ $bdt($order->shipping_bdt) }}</span></div>
    @if ($order->coupon_discount_bdt > 0)
      <div><span>Coupon {{ $order->coupon_code }}</span><span>−{{ $bdt($order->coupon_discount_bdt) }}</span></div>
    @endif
    @if ($order->points_discount_bdt > 0)
      <div><span>Points ({{ $order->points_redeemed }})</span><span>−{{ $bdt($order->points_discount_bdt) }}</span></div>
    @endif
    <div class="grand"><span>Total</span><span>{{ $bdt($order->total_bdt) }}</span></div>
  </div>

  <footer>
    Prices in Bangladeshi Taka, including import duty and Skydrop cross-border delivery. Items are bought from Indian
    stores on your behalf. Returns and delivery issues can be reported within 3 days of delivery from your account.
  </footer>
</main>
<button class="print" onclick="window.print()">Print</button>
</body>
</html>
