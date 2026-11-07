@php
    $bn = ($locale ?? 'en') === 'bn';
    $font = $bn ? "'Hind Siliguri','Noto Sans Bengali','Segoe UI',Roboto,Arial,sans-serif" : "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
    $t = $bn
        ? ['help' => 'সাহায্য দরকার?', 'reply' => 'এই ইমেইলের উত্তর দিন অথবা লিখুন', 'unsub' => 'প্রচারণামূলক ইমেইল বন্ধ করুন', 'why' => 'আপনার Skydrop Retail অ্যাকাউন্টের কারণে এই ইমেইল পেয়েছেন।', 'view' => 'অর্ডার দেখুন']
        : ['help' => 'Need help?', 'reply' => 'Reply to this email or write to', 'unsub' => 'Unsubscribe from marketing emails', 'why' => 'You are receiving this because you have a Skydrop Retail account.', 'view' => 'View order'];
@endphp
<!DOCTYPE html>
<html lang="{{ $bn ? 'bn' : 'en' }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $subject }}</title>
<style>
    body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
    table,td{mso-table-lspace:0;mso-table-rspace:0}
    img{border:0;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
    a{color:#0b6bcb}
    @media only screen and (max-width:620px){
        .container{width:100%!important}
        .px{padding-left:20px!important;padding-right:20px!important}
        .h1{font-size:22px!important;line-height:30px!important}
        .item-img{width:44px!important;height:44px!important}
        .btn a{display:block!important}
    }
    @media (prefers-color-scheme:dark){
        .bg{background:#0b1220!important}
        .card{background:#111a2b!important;border-color:#1e2a40!important}
        .ink{color:#e6edf7!important}
        .ink2{color:#a7b4c8!important}
        .line{border-color:#1e2a40!important}
        .soft{background:#16213a!important}
        .foot{color:#7f8da3!important}
    }
    [data-ogsc] .ink{color:#e6edf7!important}
    [data-ogsc] .ink2{color:#a7b4c8!important}
</style>
</head>
<body class="bg" style="margin:0;padding:0;background:#f2f5f9;font-family:{!! $font !!}">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">{{ \Illuminate\Support\Str::limit($paragraphs[0] ?? $subject, 110) }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg" style="background:#f2f5f9">
<tr><td align="center" style="padding:24px 12px">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="container" style="width:600px;max-width:600px">
        {{-- Header --}}
        <tr><td class="px" style="padding:4px 32px 18px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                <td valign="middle">
                    <a href="{{ $webUrl }}" style="text-decoration:none">
                        <img src="{{ $logo }}" width="36" height="36" alt="" style="vertical-align:middle;border-radius:8px">
                        <span class="ink" style="font-size:18px;font-weight:800;color:#0f1b2d;vertical-align:middle;margin-left:8px;letter-spacing:-.2px">Skydrop <span style="color:#ff6a3d">Retail</span></span>
                    </a>
                </td>
                @if($admin)
                    <td align="right" valign="middle"><span style="font-size:11px;font-weight:700;color:#fff;background:#e11d48;padding:4px 8px;border-radius:999px">OPS ALERT</span></td>
                @endif
            </tr></table>
        </td></tr>

        {{-- Card --}}
        <tr><td>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="card" style="background:#ffffff;border:1px solid #e3e9f1;border-radius:16px;overflow:hidden">
                <tr><td style="height:4px;background:#0b6bcb;background-image:linear-gradient(90deg,#0b6bcb,#3d8fe0 60%,#ff6a3d);font-size:0;line-height:0">&nbsp;</td></tr>
                <tr><td class="px" style="padding:28px 32px 8px">
                    <h1 class="h1 ink" style="margin:0 0 14px;font-size:24px;line-height:32px;font-weight:800;color:#0f1b2d;letter-spacing:-.3px">{{ $subject }}</h1>
                    @foreach($paragraphs as $p)
                        <p class="ink2" style="margin:0 0 14px;font-size:15px;line-height:24px;color:#3b4a60">{!! nl2br(e($p)) !!}</p>
                    @endforeach
                </td></tr>

                @if($image)
                    <tr><td class="px" style="padding:4px 32px 12px" align="center"><img src="{{ $image }}" width="200" alt="" style="max-width:200px;height:auto;border-radius:12px"></td></tr>
                @endif

                @foreach($blocks as $block)
                    @switch($block['type'] ?? '')
                        @case('items')
                            <tr><td class="px" style="padding:6px 32px 4px">
                                @foreach($block['groups'] ?? [] as $group)
                                    <p class="ink2" style="margin:12px 0 6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b7a90">{{ $group['store'] }}</p>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        @foreach($group['items'] as $it)
                                            <tr>
                                                <td width="56" valign="top" class="line" style="padding:10px 10px 10px 0;border-top:1px solid #edf1f6">
                                                    @if(!empty($it['image']))
                                                        <img class="item-img" src="{{ $it['image'] }}" width="52" height="52" alt="" style="width:52px;height:52px;object-fit:contain;border-radius:8px;background:#f6f8fb">
                                                    @else
                                                        <div class="soft" style="width:52px;height:52px;border-radius:8px;background:#eef3fa"></div>
                                                    @endif
                                                </td>
                                                <td valign="top" class="line" style="padding:10px 0;border-top:1px solid #edf1f6">
                                                    <div class="ink" style="font-size:14px;line-height:20px;color:#0f1b2d;font-weight:600">{{ \Illuminate\Support\Str::limit($it['title'], 90) }}</div>
                                                    <div class="ink2" style="font-size:12px;line-height:18px;color:#6b7a90">@if(!empty($it['variant'])){{ $it['variant'] }} · @endif{{ $it['qtyLabel'] }}</div>
                                                </td>
                                                <td valign="top" align="right" class="line ink" style="padding:10px 0 10px 10px;border-top:1px solid #edf1f6;font-size:14px;font-weight:700;color:#0f1b2d;white-space:nowrap">{{ $it['line'] }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @endforeach
                            </td></tr>
                            @break
                        @case('totals')
                            <tr><td class="px" style="padding:8px 32px 6px">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="soft" style="background:#f6f8fb;border-radius:12px">
                                    @foreach($block['rows'] ?? [] as $row)
                                        <tr>
                                            <td class="{{ !empty($row[2]) ? 'ink' : 'ink2' }}" style="padding:{{ $loop->first ? '14px' : '5px' }} 16px {{ $loop->last ? '14px' : '5px' }};font-size:{{ !empty($row[2]) ? '16px' : '14px' }};font-weight:{{ !empty($row[2]) ? '800' : '500' }};color:{{ !empty($row[2]) ? '#0f1b2d' : '#55657c' }}">{{ $row[0] }}</td>
                                            <td align="right" class="{{ !empty($row[2]) ? 'ink' : 'ink2' }}" style="padding:{{ $loop->first ? '14px' : '5px' }} 16px {{ $loop->last ? '14px' : '5px' }};font-size:{{ !empty($row[2]) ? '16px' : '14px' }};font-weight:{{ !empty($row[2]) ? '800' : '600' }};color:{{ !empty($row[2]) ? '#0f1b2d' : '#3b4a60' }};white-space:nowrap">{{ $row[1] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td></tr>
                            @break
                        @case('note')
                            @php $tone = ['warn' => ['#fff7ed', '#c2410c', '#fed7aa'], 'good' => ['#ecfdf5', '#047857', '#a7f3d0'], 'info' => ['#eff6ff', '#1d4ed8', '#bfdbfe']][$block['tone'] ?? 'info'] ?? ['#eff6ff', '#1d4ed8', '#bfdbfe']; @endphp
                            <tr><td class="px" style="padding:8px 32px 6px">
                                <div style="background:{{ $tone[0] }};border:1px solid {{ $tone[2] }};border-radius:12px;padding:12px 14px;font-size:14px;line-height:21px;color:{{ $tone[1] }};font-weight:600">{{ $block['text'] }}</div>
                            </td></tr>
                            @break
                        @case('kv')
                            <tr><td class="px" style="padding:8px 32px 6px">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    @foreach($block['rows'] ?? [] as $row)
                                        <tr>
                                            <td class="ink2 line" style="padding:7px 0;border-bottom:1px solid #edf1f6;font-size:13px;color:#6b7a90">{{ $row[0] }}</td>
                                            <td align="right" class="ink line" style="padding:7px 0;border-bottom:1px solid #edf1f6;font-size:13px;font-weight:600;color:#0f1b2d">{{ $row[1] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td></tr>
                            @break
                        @case('links')
                            <tr><td class="px" style="padding:6px 32px 2px;font-size:13px">
                                @foreach($block['links'] ?? [] as $l)
                                    <a href="{{ $l['url'] }}" style="color:#0b6bcb;font-weight:600;text-decoration:none;margin-right:16px">{{ $l['label'] }} &rarr;</a>
                                @endforeach
                            </td></tr>
                            @break
                    @endswitch
                @endforeach

                @if($cta)
                    <tr><td class="px btn" style="padding:18px 32px 30px">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                            <td style="border-radius:999px;background:#0b6bcb">
                                <a href="{{ $cta['url'] }}" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:999px;text-align:center">{{ $cta['label'] }}</a>
                            </td>
                        </tr></table>
                    </td></tr>
                @else
                    <tr><td style="height:18px;font-size:0;line-height:0">&nbsp;</td></tr>
                @endif
            </table>
        </td></tr>

        {{-- Footer --}}
        <tr><td class="px foot" style="padding:22px 32px 8px;font-size:12px;line-height:19px;color:#7a889c;text-align:center">
            @unless($admin)
                <p style="margin:0 0 6px"><strong>{{ $t['help'] }}</strong> {{ $t['reply'] }} <a href="mailto:{{ $supportEmail }}" style="color:#0b6bcb;text-decoration:none">{{ $supportEmail }}</a>@if($supportPhone) · {{ $supportPhone }}@endif</p>
            @endunless
            <p style="margin:0 0 6px">{{ $address }}</p>
            @if($marketing && $unsubscribe)
                <p style="margin:0 0 6px">{{ $t['why'] }} <a href="{{ $unsubscribe }}" style="color:#7a889c;text-decoration:underline">{{ $t['unsub'] }}</a></p>
            @endif
            <p style="margin:10px 0 0;color:#a3afc0">&copy; {{ date('Y') }} {{ $brand }}</p>
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
