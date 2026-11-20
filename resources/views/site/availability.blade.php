<x-layouts.site title="Check Availability" description="Live room availability and prices at Baseline Lodge." :noindex="request()->hasAny(['arrival', 'departure'])">
    <x-site.page-header title="Check Availability" summary="Choose your dates to see which rooms are free and what your stay will cost." />

    <div class="relative z-10 mx-auto -mt-8 max-w-6xl px-4 sm:px-6">
        <x-site.search-form :arrival="$stay?->arrivalString()" :departure="$stay?->departureString()" :adults="$adults" :children="$children" />
        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200" role="alert">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6" aria-live="polite">
        @if ($results === null)
            <p class="text-ink-900/70">Enter your arrival and departure dates above.</p>
        @else
            @php($available = collect($results)->filter->isAvailable())
            <h2 class="font-display text-2xl font-semibold text-lodge-900">
                {{ $stay->nights() }} {{ \Illuminate\Support\Str::plural('night', $stay->nights()) }},
                {{ $stay->arrival->format('D j M') }} – {{ $stay->departure->format('D j M Y') }}
            </h2>
            <p class="mt-1 text-sm text-ink-900/70">
                {{ $adults }} {{ \Illuminate\Support\Str::plural('adult', $adults) }}@if ($children), {{ $children }} {{ \Illuminate\Support\Str::plural('child', $children) }}@endif.
                Prices are for the whole stay. Availability is checked again when you book.
            </p>

            @if ($available->isEmpty())
                <div class="mt-8 rounded-2xl border border-sand-200 bg-white p-8 text-center">
                    <p class="font-semibold text-ink-900">Sorry, nothing is free for those dates and guests.</p>
                    <p class="mt-2 text-sm text-ink-900/70">Try different dates, or <a class="font-medium text-lodge-700 underline" href="{{ route('contact') }}">contact us</a> and we'll help.</p>
                </div>
            @else
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($available as $result)
                        <x-site.room-card :type="$result->type" :price="$result->quote->total()" price-label="" :price-suffix="'for '.$stay->nights().' '.\Illuminate\Support\Str::plural('night', $stay->nights())">
                            <p class="relative z-10 text-xs font-semibold text-lodge-700">
                                {{ $result->availableRooms === 1 ? 'Last room left' : $result->availableRooms.' rooms left' }}
                            </p>
                            <a href="{{ route('book', ['type' => $result->type->id, 'arrival' => $stay->arrivalString(), 'departure' => $stay->departureString(), 'adults' => $adults, 'children' => $children]) }}"
                               class="relative z-10 mt-3 inline-flex rounded-full bg-lodge-800 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-lodge-700">
                                Request this room
                            </a>
                        </x-site.room-card>
                    @endforeach
                </div>
            @endif
        @endif
    </section>
</x-layouts.site>
