@php
    /** @var array $matrix  see App\Filament\Resources\Roles\Schemas\RoleForm::matrix() */
    $abilities = $matrix['abilities'];
    $disabled = $isDisabled();
@endphp
<div
    x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        q: '',
        disabled: @js($disabled),
        has(k) { return Array.isArray(this.state) && this.state.includes(k) },
        set(k, on) {
            if (this.disabled) return;
            let s = Array.isArray(this.state) ? [...this.state] : [];
            const i = s.indexOf(k);
            if (on && i === -1) s.push(k);
            if (! on && i !== -1) s.splice(i, 1);
            this.state = s;
        },
        toggle(k) { this.set(k, ! this.has(k)) },
        all(keys) { return keys.length > 0 && keys.every(k => this.has(k)) },
        setMany(keys, on) {
            if (this.disabled) return;
            let s = Array.isArray(this.state) ? [...this.state] : [];
            keys.forEach(k => { const i = s.indexOf(k); if (on && i === -1) s.push(k); if (! on && i !== -1) s.splice(i, 1); });
            this.state = s;
        },
        toggleMany(keys) { this.setMany(keys, ! this.all(keys)) },
        visible(text) { return this.q === '' || text.toLowerCase().includes(this.q.toLowerCase()) },
        visibleKeys(selector) { return [...this.$root.querySelectorAll(selector)].filter(el => el.offsetParent !== null).map(el => el.dataset.key) },
    }"
    style="font-size:.85rem"
>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem">
        <input type="search" x-model.debounce.150ms="q" placeholder="Search permissions… (e.g. orders, refund, banner)"
               style="flex:1;min-width:14rem;padding:.45rem .7rem;border-radius:.5rem;border:1px solid rgba(127,127,127,.35);background:transparent">
        <span style="opacity:.7"><span x-text="(state || []).length"></span> keys ticked</span>
        @if ($disabled)
            <span style="color:#e11d48;font-weight:600">Read-only</span>
        @endif
    </div>

    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="text-align:left;position:sticky;top:0">
                <th style="padding:.35rem .5rem">Section</th>
                @foreach ($abilities as $ability)
                    <th style="padding:.35rem .25rem;text-align:center;white-space:nowrap">
                        <button type="button" title="Tick / untick this column for all visible rows"
                                @click="toggleMany(visibleKeys('[data-col={{ $ability }}]'))"
                                style="text-decoration:underline dotted;cursor:pointer">{{ $ability }}</button>
                    </th>
                @endforeach
                <th style="padding:.35rem .5rem">Actions</th>
                <th></th>
            </tr>
        </thead>
        @foreach ($matrix['areas'] as $area => $rows)
            <tbody x-show="{{ \Illuminate\Support\Js::from(collect($rows)->pluck('search')->all()) }}.some(t => visible(t))">
                <tr><td colspan="{{ count($abilities) + 3 }}" style="padding:.8rem .5rem .25rem;font-weight:700;text-transform:uppercase;font-size:.72rem;letter-spacing:.05em;opacity:.7">{{ $area }}</td></tr>
                @foreach ($rows as $row)
                    @php $rowKeys = array_values(array_filter([...array_values($row['abilities']), ...array_keys($row['extra'])])); @endphp
                    <tr x-show="visible(@js($row['search']))" style="border-top:1px solid rgba(127,127,127,.18)">
                        <td style="padding:.3rem .5rem">
                            <div style="font-weight:600">{{ $row['label'] }}</div>
                            <code style="font-size:.7rem;opacity:.65">{{ $row['slug'] }}</code>
                        </td>
                        @foreach ($abilities as $ability)
                            <td style="text-align:center">
                                @if ($key = $row['abilities'][$ability] ?? null)
                                    <input type="checkbox" data-key="{{ $key }}" data-col="{{ $ability }}" title="{{ $key }}"
                                           :checked="has(@js($key))" @change="toggle(@js($key))" @disabled($disabled)>
                                @endif
                            </td>
                        @endforeach
                        <td style="padding:.3rem .5rem">
                            @foreach ($row['extra'] as $key => $label)
                                <label style="display:inline-flex;gap:.25rem;align-items:center;margin:0 .6rem .15rem 0;white-space:nowrap" title="{{ $key }}">
                                    <input type="checkbox" data-key="{{ $key }}" :checked="has(@js($key))" @change="toggle(@js($key))" @disabled($disabled)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </td>
                        <td style="text-align:right;padding-right:.5rem">
                            <button type="button" @click="toggleMany(@js($rowKeys))" style="font-size:.75rem;text-decoration:underline;cursor:pointer;opacity:.8"
                                    x-text="all(@js($rowKeys)) ? 'none' : 'all'"></button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        @endforeach
    </table>

    @foreach ($matrix['lists'] as $title => $items)
        @if ($items)
            @php $listKeys = array_keys($items); @endphp
            <div x-show="{{ \Illuminate\Support\Js::from(collect($items)->map(fn ($l, $k) => $k.' '.$l.' '.$title)->values()->all()) }}.some(t => visible(t))"
                 style="margin-top:1rem;border-top:1px solid rgba(127,127,127,.25);padding-top:.6rem">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <strong>{{ $title }}</strong>
                    <button type="button" @click="toggleMany(@js($listKeys))" style="font-size:.75rem;text-decoration:underline;cursor:pointer;opacity:.8"
                            x-text="all(@js($listKeys)) ? 'Untick all' : 'Tick all'"></button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr));gap:.2rem .8rem;margin-top:.35rem">
                    @foreach ($items as $key => $label)
                        <label x-show="visible(@js($key.' '.$label.' '.$title))" style="display:flex;gap:.35rem;align-items:center" title="{{ $key }}">
                            <input type="checkbox" data-key="{{ $key }}" :checked="has(@js($key))" @change="toggle(@js($key))" @disabled($disabled)>
                            <span>{{ $label }} <code style="font-size:.7rem;opacity:.6">{{ $key }}</code></span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
