<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark">
<title>Unsubscribed · Skydrop Retail</title>
<style>
    :root{--bg:#f7f7f4;--card:#fff;--ink:#0b0b0f;--ink2:#55555f;--line:#e7e5de}
    @media (prefers-color-scheme:dark){:root{--bg:#09090c;--card:#131318;--ink:#f4f4f6;--ink2:#a8a8b3;--line:#26262f}}
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;padding:16px;box-sizing:border-box}
    .c{max-width:420px;background:var(--card);border:1px solid var(--line);border-radius:20px;padding:28px;text-align:center}
    h1{margin:0 0 8px;font-size:20px;color:var(--ink)} p{margin:0 0 16px;color:var(--ink2);line-height:1.5}
    a{display:inline-block;background:#0f766e;color:#fff;text-decoration:none;font-weight:700;padding:11px 20px;border-radius:999px}
</style>
</head>
<body>
<div class="c">
    <h1>You are unsubscribed</h1>
    <p>We will stop sending marketing {{ $channel === 'email' ? 'emails' : 'messages' }}. You will still get order, payment and delivery updates. You can change this any time in your account.</p>
    <a href="{{ $webUrl }}/account">Notification settings</a>
</div>
</body>
</html>
