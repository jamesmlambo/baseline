@php($stay = $priceList->stay())
<x-layouts.site title="Your price list" :noindex="true">
    <section class="relative overflow-hidden bg-gradient-to-br from-lodge-900 via-lodge-800 to-lodge-700 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_15%,rgba(251,191,36,0.35),transparent_40%),radial-gradient(circle_at_10%_90%,rgba(45,212,191,0.3),transparent_45%)]" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-4xl px-4 py-12 sm:px-6 sm:py-16">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">Your price list</p>
            <h1 class="mt-2 font-display text-3xl font-semibold sm:text-5xl">
                @if ($priceList->customer_name) Hi {{ $priceList->customer_name }}, here @else Here @endif are our rooms for your dates
            </h1>
            <p class="mt-4 text-lg text-sand-100">
                {{ $stay->arrival->format('D j M') }} – {{ $stay->departure->format('D j M Y') }} ·
                {{ $stay->nights() }} {{ \Illuminate\Support\Str::plural('night', $stay->nights()) }} ·
                {{ $priceList->adults }} {{ \Illuminate\Support\Str::plural('adult', $priceList->adults) }}@if ($priceList->children), {{ $priceList->children }} {{ \Illuminate\Support\Str::plural('child', $priceList->children) }}@endif
            </p>
            <p class="mt-2 text-sm text-sand-200">Live prices · link valid until {{ $priceList->expires_at->setTimezone('Africa/Johannesburg')->format('D j M, H:i') }}</p>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-10 sm:px-6" aria-live="polite">
        @if ($results->isEmpty())
            <div class="rounded-2xl border border-sand-200 bg-white p-8 text-center">
                <p class="font-semibold text-ink-900">Sorry, those dates have just filled up.</p>
                <p class="mt-2 text-sm text-ink-900/70">Reply to our WhatsApp message or <a class="font-medium text-lodge-700 underline" href="{{ route('availability') }}">check other dates</a>.</p>
            </div>
        @else
            <ul class="space-y-4">
                @foreach ($results as $result)
                    <li class="flex flex-col gap-4 rounded-2xl border border-sand-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="flex items-center gap-2 font-display text-xl font-semibold text-lodge-900">
                                {{ $result->type->name }} @if ($result->type->is_demo) <x-site.demo-badge /> @endif
                            </p>
                            @if ($result->type->summary)<p class="mt-1 text-sm text-ink-900/70">{{ $result->type->summary }}</p>@endif
                            <p class="mt-1 text-xs font-semibold text-lodge-700">{{ $result->availableRooms === 1 ? 'Last room left' : $result->availableRooms.' rooms left' }} · sleeps up to {{ $result->type->max_occupancy }}</p>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-2xl font-semibold text-ink-900">{{ $result->quote->total()->format() }}</p>
                            <p class="text-xs text-ink-900/60">for {{ $stay->nights() }} {{ \Illuminate\Support\Str::plural('night', $stay->nights()) }}, before extras</p>
                            <a href="{{ route('book', ['type' => $result->type->id, 'arrival' => $stay->arrivalString(), 'departure' => $stay->departureString(), 'adults' => $priceList->adults, 'children' => $priceList->children, 'price_list' => $priceList->token]) }}"
                               class="mt-3 inline-flex rounded-full bg-lodge-800 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-lodge-700">
                                Choose this room
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mt-6 text-sm text-ink-900/70">You can add breakfast and other extras on the next page. Rooms are only reserved once you book, and availability is checked again at that moment.</p>
        @endif

        <div class="mt-8 rounded-2xl bg-amber-50 p-4 text-sm text-ink-900/80 ring-1 ring-amber-200">
            We will never ask for your card details by WhatsApp, SMS or email.
        </div>
    </section>
</x-layouts.site>
