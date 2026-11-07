<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Test payment · Skydrop Retail</title>
<style>
  body { margin:0; min-height:100vh; display:grid; place-items:center; background:#f7f7f4; color:#0b0b0f; font:15px/1.5 "Plus Jakarta Sans", -apple-system, "Segoe UI", Roboto, sans-serif; padding:16px; }
  .card { background:#fff; max-width:420px; width:100%; border-radius:20px; padding:28px; box-shadow:0 2px 6px rgba(20,16,60,.06), 0 18px 44px rgba(20,16,60,.12); }
  .tag { display:inline-block; background:#fef3c7; color:#92400e; font-weight:700; font-size:12px; padding:2px 10px; border-radius:999px; }
  .amt { font-size:32px; font-weight:800; margin:8px 0 2px; }
  .muted { color:#55555f; }
  form { margin-top:16px; }
  button { width:100%; min-height:48px; border:0; border-radius:14px; font-weight:800; font-size:16px; cursor:pointer; }
  .ok { background:#5b3df5; color:#fff; }
  .bad { background:#fde8e9; color:#b4232a; margin-top:10px; }
</style>
</head>
<body>
<div class="card">
  <span class="tag">TEST MODE · no money is charged</span>
  <h1 style="font-size:18px;margin:14px 0 0">Skydrop Retail · {{ strtoupper($payment->payload['method'] ?? $payment->gateway) }}</h1>
  <div class="amt">৳{{ number_format($payment->amount_bdt) }}</div>
  <div class="muted">Order {{ $order->id }}@if ($payment->kind === 'advance') · 50% advance @endif</div>
  <form method="post" action="{{ $action }}">
    <input type="hidden" name="paymentId" value="{{ $payment->id }}">
    <input type="hidden" name="status" value="success">
    <input type="hidden" name="signature" value="{{ $sigSuccess }}">
    <button class="ok" type="submit">Pay successfully</button>
  </form>
  <form method="post" action="{{ $action }}">
    <input type="hidden" name="paymentId" value="{{ $payment->id }}">
    <input type="hidden" name="status" value="failed">
    <input type="hidden" name="signature" value="{{ $sigFail }}">
    <button class="bad" type="submit">Fail payment</button>
  </form>
  <form method="post" action="{{ $action }}">
    <input type="hidden" name="paymentId" value="{{ $payment->id }}">
    <input type="hidden" name="status" value="cancelled">
    <input type="hidden" name="signature" value="{{ $sigCancel }}">
    <button class="bad" type="submit" style="background:#f0efe9;color:#3a3a44">Cancel</button>
  </form>
</div>
</body>
</html>
