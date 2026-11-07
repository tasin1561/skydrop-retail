<x-filament-panels::page>
    @php $log = $this->getLog(); @endphp
    <div style="display:flex;flex-direction:column;gap:1.25rem">
        <form wire:submit="save" style="display:flex;flex-direction:column;gap:1rem">
            {{ $this->form }}
            <div><x-filament::button type="submit" icon="heroicon-o-check">Save</x-filament::button></div>
        </form>

        <x-filament::section>
            <x-slot name="heading">Caches & queue</x-slot>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <x-filament::button color="gray" icon="heroicon-o-trash" wire:click="clearCache('catalog')" wire:confirm="Clear cached search pages? Shoppers' next searches fetch live again.">Clear catalog cache</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-o-trash" wire:click="clearCache('settings')">Clear settings cache</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-o-trash" wire:click="clearCache('features')">Clear features cache</x-filament::button>
                <x-filament::button color="warning" icon="heroicon-o-arrow-path" wire:click="restartQueue" wire:confirm="Signal every queue worker to restart after its current job?">Restart queue workers</x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Log viewer {{ $log['file'] ? '· '.$log['file'] : '' }}</x-slot>
            <x-slot name="afterHeader">
                <select wire:model.live="logLines" style="padding:.25rem .5rem;border-radius:.4rem;background:transparent;border:1px solid rgba(127,127,127,.35)">
                    @foreach ([50, 100, 200, 500] as $n)<option value="{{ $n }}">Last {{ $n }} lines</option>@endforeach
                </select>
            </x-slot>
            @if (! $log['lines'])
                <div style="opacity:.7;font-size:.85rem">No log file yet.</div>
            @else
                <pre style="max-height:32rem;overflow:auto;font-size:.72rem;line-height:1.45;padding:.75rem;border-radius:.5rem;background:rgba(127,127,127,.08);white-space:pre-wrap;word-break:break-word">{{ implode("\n", $log['lines']) }}</pre>
                <div style="font-size:.75rem;opacity:.6">Read-only. Tokens, passwords, keys and configured secrets are masked.</div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
