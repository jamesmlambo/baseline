<x-layouts.site title="Price list expired" :noindex="true">
    <section class="mx-auto max-w-2xl px-4 py-24 text-center sm:px-6">
        <h1 class="font-display text-3xl font-semibold text-lodge-900">This price list has expired</h1>
        <p class="mt-4 text-ink-900/70">Prices and availability change, so price-list links only work for a limited time. Reply to our WhatsApp message for a new one, or check live availability yourself.</p>
        <a href="{{ route('availability', ['arrival' => $priceList->arrival_date->isFuture() ? $priceList->arrival_date->toDateString() : null, 'departure' => $priceList->arrival_date->isFuture() ? $priceList->departure_date->toDateString() : null, 'adults' => $priceList->adults]) }}"
           class="mt-8 inline-flex rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Check availability</a>
    </section>
</x-layouts.site>
