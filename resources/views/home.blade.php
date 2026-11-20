<x-layouts.site title="Welcome" description="Baseline Lodge guesthouse — rooms, spa, pool and braai facilities.">
    <section class="relative overflow-hidden bg-lodge-800 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(234,179,8,0.25),transparent_45%),radial-gradient(circle_at_85%_70%,rgba(20,184,166,0.35),transparent_50%)]" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">Guesthouse · South Africa</p>
            <h1 class="mt-4 max-w-2xl font-display text-4xl font-semibold leading-tight sm:text-6xl">
                Welcome to Baseline Lodge
            </h1>
            <p class="mt-6 max-w-xl text-lg text-sand-100">
                Our new website is being built. Rooms, online booking, spa, pool and braai bookings are on their way.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="font-display text-2xl font-semibold text-lodge-900">Coming soon</h2>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['Rooms & online booking', 'Spa & massage', 'Pool & day passes', 'Braai & VIP areas'] as $feature)
                <li class="rounded-2xl border border-sand-200 bg-white p-6 shadow-sm">
                    <p class="font-medium text-ink-900">{{ $feature }}</p>
                </li>
            @endforeach
        </ul>
    </section>
</x-layouts.site>
