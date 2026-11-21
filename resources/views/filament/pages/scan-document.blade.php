<x-filament-panels::page>
    @vite('resources/js/scanner.js')

    <div
        x-data="{
            running: false,
            error: null,
            stop: null,
            async startCamera() {
                this.error = null;
                try {
                    this.running = true;
                    this.stop = await window.BaselineScanner.start(this.$refs.video, this.$refs.canvas, (value) => {
                        this.running = false;
                        $wire.lookup(value);
                    });
                } catch (e) {
                    this.running = false;
                    this.error = e.message || 'Could not open the camera.';
                }
            },
            stopCamera() {
                if (this.stop) { this.stop(); }
                this.running = false;
            },
        }"
        x-on:livewire:navigating.window="stopCamera()"
        style="display:grid;gap:1.5rem;max-width:40rem;"
    >
        <x-filament::section>
            <x-slot name="heading">Scanner or keyboard</x-slot>
            <x-slot name="description">Click in the box and scan the QR on the guest's invoice, receipt or confirmation. A handheld scanner fills it in and presses Enter for you.</x-slot>

            <form wire:submit="lookup" style="display:flex;gap:.75rem;align-items:end;">
                <div style="flex:1;">
                    <label for="scanned" class="fi-fo-field-label-content" style="display:block;font-size:.875rem;font-weight:500;margin-bottom:.25rem;">Document code</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="scanned" type="text" wire:model="scanned" autofocus autocomplete="off" placeholder="Scan or paste here" />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">Find booking</x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Camera</x-slot>
            <x-slot name="description">Uses this device's camera. The image never leaves the device; only the code is looked up.</x-slot>

            <div style="display:grid;gap:.75rem;">
                <video x-ref="video" x-show="running" muted style="width:100%;max-height:22rem;border-radius:.75rem;background:#000;object-fit:cover;"></video>
                <canvas x-ref="canvas" style="display:none;"></canvas>
                <p x-show="error" x-text="error" style="color:#b91c1c;font-size:.875rem;"></p>
                <div>
                    <x-filament::button x-show="!running" x-on:click="startCamera()" icon="heroicon-o-camera" color="gray">Start camera</x-filament::button>
                    <x-filament::button x-show="running" x-on:click="stopCamera()" icon="heroicon-o-stop" color="gray">Stop camera</x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
