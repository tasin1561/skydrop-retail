<x-filament-panels::page>
    <form wire:submit="save" style="display:flex;flex-direction:column;gap:1rem">
        <p style="font-size:.85rem;opacity:.75;margin:0">
            Every limit is enforced where it applies (catalog, checkout, accounts, messaging, assistant, uploads, API). Limits marked
            with a setting name are shared with the module's own settings page. 0 means "no limit" where the help says so.
        </p>
        {{ $this->form }}
        <div style="position:sticky;bottom:0;padding:.75rem 0;display:flex;gap:.5rem;justify-content:flex-end;background:inherit">
            <x-filament::button type="submit" icon="heroicon-o-check" wire:loading.attr="disabled">Save limits</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
