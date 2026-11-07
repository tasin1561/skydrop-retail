<x-filament-panels::page>
    <style>
        .hub-wrap { display: grid; gap: 1rem; max-width: 46rem; }
        .hub-scan { display: flex; gap: .5rem; flex-wrap: wrap; }
        .hub-scan input { flex: 1 1 14rem; min-height: 48px; font-size: 1.05rem; font-family: ui-monospace, monospace; }
        .hub-btn { min-height: 48px; min-width: 48px; }
        .hub-video { position: relative; overflow: hidden; border-radius: .75rem; background: #000; aspect-ratio: 4 / 3; }
        .hub-video video { width: 100%; height: 100%; object-fit: cover; }
        .hub-video .hub-aim { position: absolute; inset: 25% 10%; border: 2px solid rgba(255,255,255,.85); border-radius: .5rem; box-shadow: 0 0 0 999px rgba(0,0,0,.35); }
        .hub-video .hub-aim::after { content: ""; position: absolute; left: 0; right: 0; top: 50%; height: 2px; background: #ff4d2e; animation: hub-sweep 1.6s ease-in-out infinite alternate; transform-origin: center; }
        @keyframes hub-sweep { from { transform: translateY(-2.5rem); } to { transform: translateY(2.5rem); } }
        @media (prefers-reduced-motion: reduce) { .hub-video .hub-aim::after { animation: none; } }
        .hub-item { display: flex; gap: .75rem; align-items: center; width: 100%; min-height: 64px; padding: .6rem .75rem; text-align: left;
            border-radius: .75rem; border: 1px solid rgba(127,127,127,.25); background: transparent; }
        .hub-item:hover, .hub-item:focus-visible { border-color: #5b3df5; outline: none; }
        .hub-item[aria-pressed="true"] { border-color: #5b3df5; box-shadow: 0 0 0 2px rgba(91,61,245,.25); }
        .hub-item img { width: 52px; height: 52px; object-fit: contain; background: #fff; border-radius: .5rem; flex: none; }
        .hub-meta { font-size: .78rem; opacity: .75; }
        .hub-pill { display: inline-flex; align-items: center; gap: .25rem; padding: .1rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 600; }
        .hub-pill.ok { background: rgba(16,185,129,.15); color: rgb(5,150,105); }
        .hub-pill.warn { background: rgba(245,158,11,.18); color: rgb(180,83,9); }
        .hub-pill.info { background: rgba(91,61,245,.12); color: #5b3df5; }
    </style>

    <div class="hub-wrap" x-data="{
            scanning: false, supported: 'BarcodeDetector' in window, stream: null, detector: null, error: null,
            async start() {
                this.error = null;
                if (!this.supported) { this.error = 'Camera scanning is not supported in this browser. Type the code instead.'; return; }
                try {
                    const formats = await BarcodeDetector.getSupportedFormats();
                    this.detector = new BarcodeDetector({ formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'qr_code', 'data_matrix'].filter(f => formats.includes(f)) });
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                    this.scanning = true;
                    await this.$nextTick();
                    this.$refs.video.srcObject = this.stream;
                    await this.$refs.video.play();
                    this.loop();
                } catch (e) { this.error = 'Could not open the camera: ' + e.message; this.stop(); }
            },
            async loop() {
                if (!this.scanning) return;
                try {
                    const codes = await this.detector.detect(this.$refs.video);
                    if (codes.length) {
                        const value = codes[0].rawValue;
                        if (navigator.vibrate) navigator.vibrate(60);
                        this.stop();
                        $wire.set('code', value);
                        $wire.lookup();
                        return;
                    }
                } catch (e) {}
                setTimeout(() => this.loop(), 180);
            },
            stop() {
                this.scanning = false;
                if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
            },
        }" x-on:hub-received.window="$refs.code.focus()" x-on:beforeunload.window="stop()">

        <x-filament::section>
            <x-slot name="heading">1. Scan or type</x-slot>
            <x-slot name="description">Marketplace order no. / AWB on the parcel, our order id (SR-…), item id (IT-…) or tracking code.</x-slot>

            <form class="hub-scan" wire:submit="lookup">
                <label for="hub-code" class="sr-only">Code</label>
                <x-filament::input.wrapper class="grow">
                    <x-filament::input id="hub-code" x-ref="code" type="text" wire:model="code" autocomplete="off" autocapitalize="characters"
                        spellcheck="false" inputmode="text" placeholder="e.g. 403-1234567-1234567" autofocus />
                </x-filament::input.wrapper>
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" class="hub-btn">Find</x-filament::button>
                <x-filament::button type="button" color="gray" icon="heroicon-o-camera" class="hub-btn" x-show="!scanning" x-on:click="start()">Scan</x-filament::button>
                <x-filament::button type="button" color="gray" icon="heroicon-o-x-mark" class="hub-btn" x-show="scanning" x-on:click="stop()" x-cloak>Stop</x-filament::button>
            </form>

            <div class="hub-video" x-show="scanning" x-cloak style="margin-top:.75rem">
                <video x-ref="video" playsinline muted></video>
                <div class="hub-aim" aria-hidden="true"></div>
            </div>
            <p x-show="error" x-text="error" x-cloak role="alert" style="margin-top:.5rem;color:rgb(225,29,72);font-size:.85rem"></p>
        </x-filament::section>

        @if ($code !== '' && $this->matches->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">2. Choose the item</x-slot>
                <div style="display:grid;gap:.5rem">
                    @foreach ($this->matches->groupBy('order_id') as $orderId => $items)
                        @php($order = $this->orders[$orderId] ?? null)
                        <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-top:.25rem">
                            <strong style="font-family:ui-monospace,monospace">{{ $orderId }}</strong>
                            <span class="hub-meta">{{ $order?->address['district'] ?? '' }}</span>
                            @if ($order?->unboxing)
                                <span class="hub-pill info">Paid unboxing: photos required</span>
                            @endif
                            @foreach ($this->progressFor($orderId) as $market => $p)
                                <span class="hub-pill {{ $p['received'] === $p['total'] ? 'ok' : 'warn' }}">{{ \App\Filament\Support\Admin::marketLabel($market) }}: {{ $p['received'] }}/{{ $p['total'] }}</span>
                            @endforeach
                        </div>
                        @foreach ($items as $it)
                            <button type="button" class="hub-item" wire:click="pick({{ $it->id }})" aria-pressed="{{ $itemId === $it->id ? 'true' : 'false' }}"
                                @disabled($it->hub_received_at)>
                                @if ($it->image)<img src="{{ $it->image }}" alt="" loading="lazy">@endif
                                <span style="min-width:0;flex:1">
                                    <span style="display:block;font-weight:600;line-height:1.3">{{ \Illuminate\Support\Str::limit($it->title, 90) }}</span>
                                    <span class="hub-meta">IT-{{ $it->id }} · Qty {{ $it->qty }}{{ $it->variant ? ' · '.$it->variant : '' }} · est. {{ number_format($it->weight_kg * $it->qty, 2) }} kg{{ $it->purchase_ref ? ' · '.$it->purchase_ref : '' }}</span>
                                </span>
                                @if ($it->hub_received_at)
                                    <span class="hub-pill ok">Received</span>
                                @endif
                            </button>
                        @endforeach
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if ($this->selected)
            <x-filament::section>
                <x-slot name="heading">3. Record IT-{{ $this->selected->id }}</x-slot>
                <x-slot name="description">{{ \Illuminate\Support\Str::limit($this->selected->title, 80) }}</x-slot>
                <form wire:submit="receive" style="display:grid;gap:1rem">
                    {{ $this->form }}
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        <x-filament::button type="submit" size="lg" icon="heroicon-o-check" class="hub-btn" style="flex:1">Mark received</x-filament::button>
                        <x-filament::button type="button" size="lg" color="gray" class="hub-btn" wire:click="clear">Cancel</x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
