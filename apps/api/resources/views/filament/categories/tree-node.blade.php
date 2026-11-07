@php $hasKids = ! empty($node['children']); $hay = strtolower($node['name'].' '.$node['path'].' '.($node['name_bn'] ?? '')); @endphp
<li data-ct-id="{{ $node['id'] }}" wire:key="ct-{{ $node['id'] }}" role="treeitem" @if($hasKids) :aria-expanded="!!open[{{ $node['id'] }}] || q !== ''" @endif
    x-show="q === '' || $el.innerText.toLowerCase().includes(q.toLowerCase()) || {{ \Illuminate\Support\Js::from($hay) }}.includes(q.toLowerCase())">
    <div class="ct-row" draggable="true"
         x-on:dragstart.self="dragId = {{ $node['id'] }}; $el.classList.add('is-dragging'); $event.dataTransfer.effectAllowed = 'move'"
         x-on:dragend.self="$el.classList.remove('is-dragging'); dragId = null"
         x-on:dragover.prevent="$el.classList.add('is-over')"
         x-on:dragleave="$el.classList.remove('is-over')"
         x-on:drop.prevent="$el.classList.remove('is-over'); drop($el.closest('ul'), {{ $node['id'] }})">
        <span class="ct-btn ct-handle" aria-hidden="true" title="Drag to reorder">
            <x-filament::icon icon="heroicon-m-bars-2" style="width:1rem;height:1rem" />
        </span>
        @if ($hasKids)
            <button type="button" class="ct-btn" x-on:click="toggle({{ $node['id'] }})" :aria-label="(open[{{ $node['id'] }}] ? 'Collapse ' : 'Expand ') + {{ \Illuminate\Support\Js::from($node['name']) }}">
                <x-filament::icon icon="heroicon-m-chevron-right" class="ct-chev" x-bind:class="{ 'is-open': open[{{ $node['id'] }}] || q !== '' }" style="width:1rem;height:1rem" />
            </button>
        @else
            <span class="ct-btn" aria-hidden="true"></span>
        @endif
        <div style="min-width:0;flex:1">
            <a href="{{ $node['edit'] }}" style="font-weight:600;{{ $node['active'] ? '' : 'opacity:.5;text-decoration:line-through' }}">{{ $node['name'] }}</a>
            @if ($node['name_bn']) <span class="ct-meta" lang="bn">· {{ $node['name_bn'] }}</span> @endif
            <div class="ct-meta">{{ $node['path'] }} · {{ $node['icon'] }}</div>
        </div>
        @if ($node['featured']) <span class="ct-chip">featured</span> @endif
        @foreach ($node['stores'] as $s) <span class="ct-chip">{{ $s }}</span> @endforeach
        <span class="ct-meta" style="min-width:3rem;text-align:right">{{ number_format($node['count']) }}</span>
        <button type="button" class="ct-btn" wire:click="shift({{ $node['id'] }}, -1)" aria-label="Move {{ $node['name'] }} up">
            <x-filament::icon icon="heroicon-m-arrow-up" style="width:1rem;height:1rem" />
        </button>
        <button type="button" class="ct-btn" wire:click="shift({{ $node['id'] }}, 1)" aria-label="Move {{ $node['name'] }} down">
            <x-filament::icon icon="heroicon-m-arrow-down" style="width:1rem;height:1rem" />
        </button>
    </div>
    @if ($hasKids)
        <ul class="ct-list" role="group" x-show="open[{{ $node['id'] }}] || q !== ''" x-cloak>
            @foreach ($node['children'] as $child)
                @include('filament.categories.tree-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
