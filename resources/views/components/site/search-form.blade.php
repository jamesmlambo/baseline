@props([
    'arrival' => null,
    'departure' => null,
    'adults' => 2,
    'children' => 0,
    'compact' => false,
])
@php
    $today = today()->toDateString();
    $arrivalValue = old('arrival', $arrival ?? request('arrival'));
    $departureValue = old('departure', $departure ?? request('departure'));
@endphp
<form method="GET" action="{{ route('availability') }}" {{ $attributes->class([
    'grid gap-3 rounded-2xl bg-white p-4 shadow-lg ring-1 ring-sand-200 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_auto_auto] lg:items-end',
]) }} role="search" aria-label="Check availability">
    <div>
        <label for="arrival" class="block text-xs font-semibold uppercase tracking-wide text-ink-900/70">Arrival</label>
        <input id="arrival" name="arrival" type="date" min="{{ $today }}" value="{{ $arrivalValue }}" required
            class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-2.5 text-ink-900 focus:border-lodge-700 focus:ring-2 focus:ring-lodge-700/30 focus:outline-none">
    </div>
    <div>
        <label for="departure" class="block text-xs font-semibold uppercase tracking-wide text-ink-900/70">Departure</label>
        <input id="departure" name="departure" type="date" min="{{ $today }}" value="{{ $departureValue }}" required
            class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-2.5 text-ink-900 focus:border-lodge-700 focus:ring-2 focus:ring-lodge-700/30 focus:outline-none">
    </div>
    <div>
        <label for="adults" class="block text-xs font-semibold uppercase tracking-wide text-ink-900/70">Adults</label>
        <select id="adults" name="adults" class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-2.5 focus:border-lodge-700 focus:ring-2 focus:ring-lodge-700/30 focus:outline-none">
            @foreach (range(1, 8) as $n)
                <option value="{{ $n }}" @selected((int) old('adults', $adults) === $n)>{{ $n }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="children" class="block text-xs font-semibold uppercase tracking-wide text-ink-900/70">Children</label>
        <select id="children" name="children" class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-2.5 focus:border-lodge-700 focus:ring-2 focus:ring-lodge-700/30 focus:outline-none">
            @foreach (range(0, 6) as $n)
                <option value="{{ $n }}" @selected((int) old('children', $children) === $n)>{{ $n }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="rounded-lg bg-lodge-800 px-6 py-3 font-semibold text-white transition hover:bg-lodge-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lodge-700 sm:col-span-2 lg:col-span-1">
        Search
    </button>
</form>
