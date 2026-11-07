<x-filament-panels::page>
    @php $tree = $this->getTree(); @endphp
    <style>
        .ct-list { list-style: none; margin: 0; padding: 0; }
        .ct-list .ct-list { margin-left: 1.25rem; border-left: 1px dashed rgba(127,127,127,.3); padding-left: .5rem; }
        .ct-row { display: flex; align-items: center; gap: .5rem; min-height: 2.75rem; padding: .25rem .5rem; border-radius: .5rem; }
        .ct-row:hover { background: rgba(127,127,127,.08); }
        .ct-row.is-over { box-shadow: inset 0 2px 0 rgb(var(--primary-500, 59 130 246)); }
        .ct-row.is-dragging { opacity: .45; }
        .ct-btn { display: inline-grid; place-items: center; width: 2rem; height: 2rem; border-radius: .4rem; opacity: .7; }
        .ct-btn:hover, .ct-btn:focus-visible { opacity: 1; background: rgba(127,127,127,.15); }
        .ct-handle { cursor: grab; }
        .ct-chev { transition: transform .2s ease; }
        .ct-chev.is-open { transform: rotate(90deg); }
        .ct-meta { font-size: .75rem; opacity: .65; }
        .ct-chip { font-size: .7rem; padding: .05rem .4rem; border-radius: 999px; background: rgba(127,127,127,.15); }
        @media (prefers-reduced-motion: reduce) { .ct-chev { transition: none; } }
    </style>
    <div x-data="{
            q: '',
            open: {},
            dragId: null,
            toggle(id) { this.open[id] = !this.open[id] },
            all(v) { document.querySelectorAll('[data-ct-id]').forEach(el => this.open[el.dataset.ctId] = v) },
            drop(list, targetId) {
                if (!this.dragId || this.dragId == targetId) return;
                const ids = [...list.children].map(li => li.dataset.ctId);
                if (!ids.includes(String(this.dragId))) { $wire.reorder([this.dragId, targetId]); return; }
                const from = ids.indexOf(String(this.dragId)); ids.splice(from, 1);
                ids.splice(ids.indexOf(String(targetId)), 0, String(this.dragId));
                $wire.reorder(ids);
            },
        }" style="display:flex;flex-direction:column;gap:1rem">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <input type="search" x-model.debounce.150ms="q" placeholder="Filter by name or path…" aria-label="Filter categories"
                   style="flex:1;min-width:14rem;padding:.5rem .75rem;border-radius:.5rem;border:1px solid rgba(127,127,127,.35);background:transparent">
            <x-filament::button color="gray" size="sm" x-on:click="all(true)">Expand all</x-filament::button>
            <x-filament::button color="gray" size="sm" x-on:click="all(false)">Collapse all</x-filament::button>
        </div>
        <p class="ct-meta">Drag a row by its handle to reorder it among its siblings, or use the arrows. Use "Move to…" on the edit page to change the parent.</p>
        <x-filament::section>
            <ul class="ct-list" role="tree" aria-label="Category tree">
                @foreach ($tree as $node)
                    @include('filament.categories.tree-node', ['node' => $node])
                @endforeach
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
