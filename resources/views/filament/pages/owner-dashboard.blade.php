<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="preset" class="block text-sm font-medium">Period</label>
            <select id="preset" wire:model.live="preset" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900">
                @foreach (\App\Filament\Pages\OwnerDashboard::PRESETS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if ($preset === 'custom')
            <div>
                <label for="from" class="block text-sm font-medium">From</label>
                <input id="from" type="date" wire:model.live="from" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900">
            </div>
            <div>
                <label for="until" class="block text-sm font-medium">To</label>
                <input id="until" type="date" wire:model.live="until" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900">
            </div>
        @endif
        <span wire:loading class="text-sm text-gray-500">Updating…</span>
    </div>

    @include('filament.reports.metrics', ['data' => $data])
</x-filament-panels::page>
