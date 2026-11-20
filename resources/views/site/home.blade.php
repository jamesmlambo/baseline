@php
    $site = app(\App\Support\SiteContent::class);
    $contact = $site->contact();
    $lodgingSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'LodgingBusiness',
        'name' => config('app.name'),
        'url' => route('home'),
        'telephone' => $contact['phone'],
        'email' => $contact['email'],
        'address' => $contact['address'] ? ['@type' => 'PostalAddress', 'streetAddress' => $contact['address'], 'addressCountry' => 'ZA'] : null,
        'currenciesAccepted' => 'ZAR',
        'starRating' => $site->gradingStars() ? ['@type' => 'Rating', 'ratingValue' => $site->gradingStars()] : null,
    ]);
@endphp
<x-layouts.site title="Guesthouse" :description="$tagline ?? 'Baseline Lodge guesthouse: rooms, spa, pool, braai and VIP areas.'">
    @push('head')
        <script type="application/ld+json">{!! json_encode($lodgingSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endpush

    <section class="relative overflow-hidden bg-lodge-800 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(234,179,8,0.25),transparent_45%),radial-gradient(circle_at_85%_70%,rgba(20,184,166,0.35),transparent_50%)]" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-6xl px-4 pb-28 pt-20 sm:px-6 sm:pt-28">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">Guesthouse · South Africa</p>
            <h1 class="mt-4 max-w-3xl font-display text-4xl font-semibold leading-tight sm:text-6xl">
                Welcome to {{ config('app.name') }}
            </h1>
            @if ($tagline)
                <p class="mt-6 max-w-xl text-lg text-sand-100">{{ $tagline }}</p>
            @endif
        </div>
    </section>

    <div class="relative z-10 mx-auto -mt-16 max-w-6xl px-4 sm:px-6">
        <h2 class="sr-only">Check availability</h2>
        <x-site.search-form />
    </div>

    @if ($intro)
        <section class="mx-auto max-w-3xl px-4 pt-16 text-center sm:px-6">
            <p class="text-lg leading-relaxed text-ink-900/80">{{ $intro }}</p>
        </section>
    @endif

    @if ($types->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6" aria-labelledby="rooms-heading">
            <div class="flex items-end justify-between gap-4">
                <h2 id="rooms-heading" class="font-display text-3xl font-semibold text-lodge-900">Our rooms</h2>
                <a href="{{ route('rooms.index') }}" class="text-sm font-semibold text-lodge-700 hover:text-lodge-900">All rooms →</a>
            </div>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($types as $type)
                    <x-site.room-card :type="$type" :price="$fromPrices[$type->id] ?? null" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6" aria-labelledby="facilities-heading">
        <h2 id="facilities-heading" class="font-display text-3xl font-semibold text-lodge-900">Relax and unwind</h2>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['pool', 'braai', 'vip', 'spa'] as $key)
                @if ($page = $site->page($key))
                    <a href="{{ route('page', $page->slug) }}" class="group rounded-2xl border border-sand-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <p class="font-display text-lg font-semibold text-lodge-900 group-hover:text-lodge-700">{{ $page->title }}</p>
                        @if ($page->summary)
                            <p class="mt-2 text-sm text-ink-900/70">{{ $page->summary }}</p>
                        @endif
                    </a>
                @endif
            @endforeach
        </div>
        @if ($facilities->isNotEmpty())
            <ul class="mt-6 flex flex-wrap gap-2" aria-label="Facilities">
                @foreach ($facilities as $facility)
                    <li class="rounded-full bg-sand-100 px-4 py-1.5 text-sm text-ink-900/80">{{ $facility->name }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($posts->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6" aria-labelledby="news-heading">
            <div class="flex items-end justify-between gap-4">
                <h2 id="news-heading" class="font-display text-3xl font-semibold text-lodge-900">News &amp; events</h2>
                <a href="{{ route('news.index') }}" class="text-sm font-semibold text-lodge-700 hover:text-lodge-900">All news →</a>
            </div>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-site.post-card :post="$post" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6">
        <div class="flex flex-col items-start gap-6 rounded-3xl bg-lodge-900 p-8 text-white sm:flex-row sm:items-center sm:justify-between sm:p-12">
            <div>
                <h2 class="font-display text-2xl font-semibold sm:text-3xl">Ready to stay with us?</h2>
                <p class="mt-2 text-sand-200">Check live availability and prices for your dates.</p>
            </div>
            <a href="{{ route('availability') }}" class="rounded-full bg-amber-400 px-6 py-3 font-semibold text-ink-900 transition hover:bg-amber-300">Check availability</a>
        </div>
    </section>
</x-layouts.site>
