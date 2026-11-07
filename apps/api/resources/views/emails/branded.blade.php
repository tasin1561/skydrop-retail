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
    a{color:#5b3df5}
    @media only screen and (max-width:620px){
        .container{width:100%!important}
        .px{padding-left:20px!important;padding-right:20px!important}
        .h1{font-size:22px!important;line-height:30px!important}
        .item-img{width:44px!important;height:44px!important}
        .btn a{display:block!important}
    }
    @media (prefers-color-scheme:dark){
        .bg{background:#09090c!important}
        .card{background:#131318!important;border-color:#26262f!important}
        .ink{color:#f4f4f6!important}
        .ink2{color:#a8a8b3!important}
        .line{border-color:#26262f!important}
        .soft{background:#1b1b22!important}
        .foot{color:#8a8a96!important}
        .lnk{color:#a78bfa!important}
        .hdr{background:#131318!important}
    }
    [data-ogsc] .lnk{color:#a78bfa!important}
    [data-ogsc] .ink{color:#f4f4f6!important}
    [data-ogsc] .ink2{color:#a8a8b3!important}
</style>
</head>
<body class="bg" style="margin:0;padding:0;background:#f7f7f4;font-family:{!! $font !!}">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">{{ \Illuminate\Support\Str::limit($paragraphs[0] ?? $subject, 110) }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg" style="background:#f7f7f4">
<tr><td align="center" style="padding:24px 12px">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="container" style="width:600px;max-width:600px">
        {{-- Header --}}
        <tr><td style="padding:0 0 14px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="hdr" style="background:#0b0b0f;border-radius:20px"><tr><td class="px" style="padding:16px 24px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                <td valign="middle">
                    <a href="{{ $webUrl }}" style="text-decoration:none">
                        <img src="{{ $logo }}" width="36" height="36" alt="" style="vertical-align:middle;border-radius:8px">
                        <span style="font-size:18px;font-weight:800;color:#ffffff;vertical-align:middle;margin-left:8px;letter-spacing:-.2px">Skydrop <span style="color:#ff4d2e">Retail</span></span>
                    </a>
                </td>
                @if($admin)
                    <td align="right" valign="middle"><span style="font-size:11px;font-weight:700;color:#fff;background:#e5363d;padding:4px 8px;border-radius:999px">OPS ALERT</span></td>
                @endif
            </tr></table>
            </td></tr></table>
        </td></tr>

        {{-- Card --}}
        <tr><td>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="card" style="background:#ffffff;border:1px solid #e7e5de;border-radius:20px;overflow:hidden">
                <tr><td style="height:4px;background:#5b3df5;background-image:linear-gradient(90deg,#5b3df5,#a78bfa 70%,#ff4d2e);font-size:0;line-height:0">&nbsp;</td></tr>
                <tr><td class="px" style="padding:28px 32px 8px">
                    <h1 class="h1 ink" style="margin:0 0 14px;font-size:24px;line-height:32px;font-weight:800;color:#0b0b0f;letter-spacing:-.3px">{{ $subject }}</h1>
                    @foreach($paragraphs as $p)
                        <p class="ink2" style="margin:0 0 14px;font-size:15px;line-height:24px;color:#3a3a44">{!! nl2br(e($p)) !!}</p>
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
                                    <p class="ink2" style="margin:12px 0 6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b6b78">{{ $group['store'] }}</p>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        @foreach($group['items'] as $it)
                                            <tr>
                                                <td width="56" valign="top" class="line" style="padding:10px 10px 10px 0;border-top:1px solid #efeee8">
                                                    @if(!empty($it['image']))
                                                        <img class="item-img" src="{{ $it['image'] }}" width="52" height="52" alt="" style="width:52px;height:52px;object-fit:contain;border-radius:8px;background:#f7f7f4">
                                                    @else
                                                        <div class="soft" style="width:52px;height:52px;border-radius:8px;background:#f0efe9"></div>
                                                    @endif
                                                </td>
                                                <td valign="top" class="line" style="padding:10px 0;border-top:1px solid #efeee8">
                                                    <div class="ink" style="font-size:14px;line-height:20px;color:#0b0b0f;font-weight:600">{{ \Illuminate\Support\Str::limit($it['title'], 90) }}</div>
                                                    <div class="ink2" style="font-size:12px;line-height:18px;color:#6b6b78">@if(!empty($it['variant'])){{ $it['variant'] }} · @endif{{ $it['qtyLabel'] }}</div>
                                                </td>
                                                <td valign="top" align="right" class="line ink" style="padding:10px 0 10px 10px;border-top:1px solid #efeee8;font-size:14px;font-weight:700;color:#0b0b0f;white-space:nowrap">{{ $it['line'] }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @endforeach
                            </td></tr>
                            @break
                        @case('totals')
                            <tr><td class="px" style="padding:8px 32px 6px">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="soft" style="background:#f7f7f4;border-radius:12px">
                                    @foreach($block['rows'] ?? [] as $row)
                                        <tr>
                                            <td class="{{ !empty($row[2]) ? 'ink' : 'ink2' }}" style="padding:{{ $loop->first ? '14px' : '5px' }} 16px {{ $loop->last ? '14px' : '5px' }};font-size:{{ !empty($row[2]) ? '16px' : '14px' }};font-weight:{{ !empty($row[2]) ? '800' : '500' }};color:{{ !empty($row[2]) ? '#0b0b0f' : '#55555f' }}">{{ $row[0] }}</td>
                                            <td align="right" class="{{ !empty($row[2]) ? 'ink' : 'ink2' }}" style="padding:{{ $loop->first ? '14px' : '5px' }} 16px {{ $loop->last ? '14px' : '5px' }};font-size:{{ !empty($row[2]) ? '16px' : '14px' }};font-weight:{{ !empty($row[2]) ? '800' : '600' }};color:{{ !empty($row[2]) ? '#0b0b0f' : '#3a3a44' }};white-space:nowrap">{{ $row[1] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td></tr>
                            @break
                        @case('note')
                            @php $tone = ['warn' => ['#fff7ed', '#c2410c', '#fed7aa'], 'good' => ['#ecfdf5', '#047857', '#a7f3d0'], 'info' => ['#f3f0ff', '#4b2ee0', '#d9d1fd']][$block['tone'] ?? 'info'] ?? ['#f3f0ff', '#4b2ee0', '#d9d1fd']; @endphp
                            <tr><td class="px" style="padding:8px 32px 6px">
                                <div style="background:{{ $tone[0] }};border:1px solid {{ $tone[2] }};border-radius:12px;padding:12px 14px;font-size:14px;line-height:21px;color:{{ $tone[1] }};font-weight:600">{{ $block['text'] }}</div>
                            </td></tr>
                            @break
                        @case('kv')
                            <tr><td class="px" style="padding:8px 32px 6px">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    @foreach($block['rows'] ?? [] as $row)
                                        <tr>
                                            <td class="ink2 line" style="padding:7px 0;border-bottom:1px solid #efeee8;font-size:13px;color:#6b6b78">{{ $row[0] }}</td>
                                            <td align="right" class="ink line" style="padding:7px 0;border-bottom:1px solid #efeee8;font-size:13px;font-weight:600;color:#0b0b0f">{{ $row[1] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td></tr>
                            @break
                        @case('links')
                            <tr><td class="px" style="padding:6px 32px 2px;font-size:13px">
                                @foreach($block['links'] ?? [] as $l)
                                    <a class="lnk" href="{{ $l['url'] }}" style="color:#5b3df5;font-weight:600;text-decoration:none;margin-right:16px">{{ $l['label'] }} &rarr;</a>
                                @endforeach
                            </td></tr>
                            @break
                    @endswitch
                @endforeach

                @if($cta)
                    <tr><td class="px btn" style="padding:18px 32px 30px">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                            <td style="border-radius:999px;background:#5b3df5">
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
        <tr><td class="px foot" style="padding:22px 32px 8px;font-size:12px;line-height:19px;color:#7a7a85;text-align:center">
            @unless($admin)
                <p style="margin:0 0 6px"><strong>{{ $t['help'] }}</strong> {{ $t['reply'] }} <a class="lnk" href="mailto:{{ $supportEmail }}" style="color:#5b3df5;text-decoration:none">{{ $supportEmail }}</a>@if($supportPhone) · {{ $supportPhone }}@endif</p>
            @endunless
            <p style="margin:0 0 6px">{{ $address }}</p>
            @if($marketing && $unsubscribe)
                <p style="margin:0 0 6px">{{ $t['why'] }} <a href="{{ $unsubscribe }}" style="color:#7a7a85;text-decoration:underline">{{ $t['unsub'] }}</a></p>
            @endif
            <p style="margin:10px 0 0;color:#9a9aa3">&copy; {{ date('Y') }} {{ $brand }}</p>
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
