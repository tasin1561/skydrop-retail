<x-filament-panels::page>
    @php
        $groups = $this->getGroups();
        $meta = $this->getMeta();
    @endphp
    <div x-data="{ q: '' }" style="display:flex;flex-direction:column;gap:1.25rem">
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
            <input type="search" x-model.debounce.150ms="q" placeholder="Search switches…"
                   style="flex:1;min-width:14rem;padding:.5rem .75rem;border-radius:.5rem;border:1px solid rgba(127,127,127,.35);background:transparent">
            <span style="opacity:.7;font-size:.85rem">{{ count(array_filter($states)) }} of {{ count($states) }} on</span>
        </div>

        @foreach ($groups as $group => $switches)
            @php $search = collect($switches)->map(fn ($s, $k) => strtolower($k.' '.$s['label'].' '.$s['description'].' '.$group))->values()->all(); @endphp
            <x-filament::section x-show="q === '' || {{ \Illuminate\Support\Js::from($search) }}.some(t => t.includes(q.toLowerCase()))">
                <x-slot name="heading">{{ $group }}</x-slot>
                <x-slot name="afterHeader">
                    <div style="display:flex;gap:.75rem">
                        {{ ($this->groupAction)(['section' => $group, 'on' => true]) }}
                        {{ ($this->groupAction)(['section' => $group, 'on' => false]) }}
                    </div>
                </x-slot>

                <div style="display:flex;flex-direction:column">
                    @foreach ($switches as $key => $s)
                        @php $on = $states[$key] ?? false; @endphp
                        <div wire:key="feature-{{ $key }}"
                             x-show="q === '' || {{ \Illuminate\Support\Js::from(strtolower($key.' '.$s['label'].' '.$s['description'].' '.$group)) }}.includes(q.toLowerCase())"
                             style="display:flex;gap:1rem;align-items:center;justify-content:space-between;padding:.6rem 0;border-top:1px solid rgba(127,127,127,.15)">
                            <div style="min-width:0">
                                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                                    <strong>{{ $s['label'] }}</strong>
                                    <x-filament::badge :color="$on ? 'success' : 'danger'" size="sm">{{ $on ? 'ON' : 'OFF' }}</x-filament::badge>
                                    @if (\App\Filament\Pages\FeatureSwitches::isRisky($key))
                                        <x-filament::badge color="warning" size="sm">confirm</x-filament::badge>
                                    @endif
                                    <code style="font-size:.72rem;opacity:.6">{{ $key }}</code>
                                </div>
                                @if ($s['description'])
                                    <div style="font-size:.82rem;opacity:.75">{{ $s['description'] }}</div>
                                @endif
                                <div style="font-size:.75rem;opacity:.6">{{ $meta[$key] ?? 'Never changed (default '.($s['default'] ? 'ON' : 'OFF').')' }}</div>
                            </div>
                            <div style="flex-shrink:0">{{ ($this->toggleAction)(['key' => $key]) }}</div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
