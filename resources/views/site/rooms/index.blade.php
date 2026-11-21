<x-layouts.site title="Our Rooms" description="Rooms at Baseline Lodge, with live prices.">
    <x-site.page-header title="Our Rooms" summary="Every room type, with today's price for one night." />

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if ($types->isEmpty())
            <p class="text-ink-900/70">Room information is coming soon. Please <a class="font-medium text-lodge-700 underline" href="{{ route('contact') }}">contact us</a>.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($types as $type)
                    <x-site.room-card :type="$type" :price="$fromPrices[$type->id] ?? null" :heading-level="2" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.site>
