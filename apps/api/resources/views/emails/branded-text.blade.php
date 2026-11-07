{!! $subject !!}

@foreach($paragraphs as $p)
{!! $p !!}

@endforeach
@foreach($blocks as $block)
@if(($block['type'] ?? '') === 'items')
@foreach($block['groups'] ?? [] as $group)
{!! $group['store'] !!}
@foreach($group['items'] as $it)
- {!! $it['title'] !!}@if(!empty($it['variant'])) ({!! $it['variant'] !!})@endif · {!! $it['qtyLabel'] !!} · {!! $it['line'] !!}
@endforeach

@endforeach
@elseif(($block['type'] ?? '') === 'totals' || ($block['type'] ?? '') === 'kv')
@foreach($block['rows'] ?? [] as $row)
{!! $row[0] !!}: {!! $row[1] !!}
@endforeach

@elseif(($block['type'] ?? '') === 'note')
{!! $block['text'] !!}

@elseif(($block['type'] ?? '') === 'links')
@foreach($block['links'] ?? [] as $l)
{!! $l['label'] !!}: {!! $l['url'] !!}
@endforeach

@endif
@endforeach
@if($cta)
{!! $cta['label'] !!}: {!! $cta['url'] !!}

@endif
--
{!! $brand !!} · {!! $address !!}
@if(!$admin)Support: {!! $supportEmail !!}
@endif
@if($marketing && $unsubscribe)Unsubscribe: {!! $unsubscribe !!}
@endif
