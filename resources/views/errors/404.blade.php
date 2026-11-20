<x-layouts.site title="Page not found" :noindex="true">
    <section class="mx-auto max-w-2xl px-4 py-24 text-center sm:px-6">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-lodge-700">404</p>
        <h1 class="mt-3 font-display text-4xl font-semibold text-lodge-900">We can't find that page</h1>
        <p class="mt-4 text-ink-900/70">It may have moved when we updated our website.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('home') }}" class="rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Go to the homepage</a>
            <a href="{{ route('rooms.index') }}" class="rounded-full border border-sand-300 px-6 py-3 font-semibold text-ink-900 hover:bg-sand-100">See our rooms</a>
        </div>
    </section>
</x-layouts.site>
