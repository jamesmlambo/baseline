@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'canonical' => null,
    'noindex' => false,
])
@php
    $site = app(\App\Support\SiteContent::class);
    $siteName = config('app.name');
    $pageTitle = $title ? $title.' · '.$siteName : $siteName;
    $metaDescription = \Illuminate\Support\Str::limit(strip_tags((string) ($description ?? \App\Models\Setting::get('website.tagline') ?? $siteName.' guesthouse')), 160);
    $canonicalUrl = $canonical ?? url()->current();
    $contact = $site->contact();
    $whatsApp = $site->whatsAppUrl('Hello Baseline Lodge, I would like to ask about a booking.');

    $mainNav = [
        ['label' => 'Rooms', 'url' => route('rooms.index'), 'active' => request()->routeIs('rooms.*')],
        ['label' => $site->pageLabel('services') ?? 'Services', 'url' => $site->pageUrl('services'), 'active' => false],
        ['label' => $site->pageLabel('spa') ?? 'Spa', 'url' => $site->pageUrl('spa'), 'active' => false],
        ['label' => 'News & events', 'url' => route('news.index'), 'active' => request()->routeIs('news.*')],
        ['label' => $site->pageLabel('about') ?? 'About', 'url' => $site->pageUrl('about'), 'active' => false],
        ['label' => 'Contact', 'url' => route('contact'), 'active' => request()->routeIs('contact')],
    ];
    $mainNav = array_values(array_filter($mainNav, fn (array $item): bool => $item['url'] !== null));
    foreach ($mainNav as $i => $item) {
        $mainNav[$i]['active'] = $item['active'] || rtrim($item['url'], '/') === rtrim(url()->current(), '/');
    }

    $footerKeys = ['about', 'features', 'services', 'pool', 'braai', 'vip', 'spa', 'guest-portal'];
    $policyKeys = ['house-rules', 'policies', 'privacy', 'communication-policy'];
@endphp
<!DOCTYPE html>
<html lang="en-ZA" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @if ($noindex || ! app()->isProduction())
        <meta name="robots" content="noindex, nofollow">
    @endif

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? $siteName }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:locale" content="en_ZA">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif

    <meta name="theme-color" content="#0f514b">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-sand-50 font-sans text-ink-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">
        Skip to content
    </a>

    @unless (app()->isProduction())
        <div class="bg-amber-400 px-4 py-1.5 text-center text-xs font-semibold tracking-wide text-ink-900" role="note">
            DEMO — content, prices and photos are placeholders. Replace before launch.
        </div>
    @endunless

    <header class="sticky top-0 z-40 border-b border-sand-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="font-display text-xl font-semibold tracking-tight text-lodge-800 sm:text-2xl">
                {{ $siteName }}
            </a>

            <nav aria-label="Main" class="hidden items-center gap-1 text-sm font-medium lg:flex">
                @foreach ($mainNav as $item)
                    <a href="{{ $item['url'] }}" @class([
                        'rounded-full px-3 py-2 transition hover:bg-sand-100 focus-visible:outline-2 focus-visible:outline-lodge-700',
                        'bg-sand-100 text-lodge-800' => $item['active'],
                    ]) @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
                <a href="{{ route('availability') }}" class="ml-2 rounded-full bg-lodge-800 px-4 py-2 font-semibold text-white transition hover:bg-lodge-700">
                    Check availability
                </a>
            </nav>

            <details class="group relative lg:hidden">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-sand-200 px-4 py-2 text-sm font-medium [&::-webkit-details-marker]:hidden">
                    <span>Menu</span>
                    <svg class="size-4 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                </summary>
                <nav aria-label="Main (mobile)" class="absolute right-0 mt-2 w-64 rounded-2xl border border-sand-200 bg-white p-2 shadow-xl">
                    @foreach ($mainNav as $item)
                        <a href="{{ $item['url'] }}" class="block rounded-xl px-4 py-3 text-sm font-medium hover:bg-sand-100" @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                    @endforeach
                    <a href="{{ route('availability') }}" class="mt-1 block rounded-xl bg-lodge-800 px-4 py-3 text-center text-sm font-semibold text-white">Check availability</a>
                </nav>
            </details>
        </div>
    </header>

    @if (session('status'))
        <div class="border-b border-lodge-700/20 bg-lodge-800/5" role="status">
            <p class="mx-auto max-w-6xl px-4 py-4 text-sm font-medium text-lodge-900 sm:px-6">{{ session('status') }}</p>
        </div>
    @endif

    <main id="main" class="flex-1" tabindex="-1">
        {{ $slot }}
    </main>

    @if ($whatsApp)
        <a href="{{ $whatsApp }}" target="_blank" rel="noopener" class="fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-full bg-[#1f8f4e] px-5 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-[#187a41]">
            <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.8 1c-.1.2-.3.2-.5.1a6.6 6.6 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.5-.4h-.5a.9.9 0 0 0-.6.3 2.7 2.7 0 0 0-.9 2c0 1.2.9 2.4 1 2.5.1.2 1.7 2.7 4.2 3.8 1.6.7 2.2.7 3 .6.5-.1 1.4-.6 1.6-1.2.2-.6.2-1 .1-1.2l-.5-.2Z"/></svg>
            <span>WhatsApp us</span>
        </a>
    @endif

    <footer class="mt-16 bg-lodge-900 text-sand-100">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
            <div>
                <p class="font-display text-lg font-semibold">{{ $siteName }}</p>
                @if ($stars = $site->gradingStars())
                    <p class="mt-2 text-sm text-amber-300" aria-label="{{ $stars }}-star graded">{{ str_repeat('★', $stars) }} graded</p>
                @endif
                <address class="mt-3 space-y-1 text-sm not-italic text-sand-300">
                    @if ($contact['address'])<p>{{ $contact['address'] }}</p>@endif
                    @if ($contact['phone'])<p><a class="hover:text-white" href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></p>@endif
                    @if ($contact['email'])<p><a class="hover:text-white" href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>@endif
                    @unless ($contact['address'] || $contact['phone'] || $contact['email'])
                        <p>Contact details coming soon.</p>
                    @endunless
                </address>
            </div>
            <nav aria-label="Explore" class="text-sm">
                <p class="font-semibold text-white">Explore</p>
                <ul class="mt-3 space-y-2 text-sand-300">
                    <li><a class="hover:text-white" href="{{ route('rooms.index') }}">Rooms</a></li>
                    @foreach ($footerKeys as $key)
                        @if ($url = $site->pageUrl($key))
                            <li><a class="hover:text-white" href="{{ $url }}">{{ $site->pageLabel($key) }}</a></li>
                        @endif
                    @endforeach
                </ul>
            </nav>
            <nav aria-label="Guest information" class="text-sm">
                <p class="font-semibold text-white">Guest information</p>
                <ul class="mt-3 space-y-2 text-sand-300">
                    <li><a class="hover:text-white" href="{{ route('faqs') }}">FAQs</a></li>
                    <li><a class="hover:text-white" href="{{ route('news.index') }}">News &amp; events</a></li>
                    @foreach ($policyKeys as $key)
                        @if ($url = $site->pageUrl($key))
                            <li><a class="hover:text-white" href="{{ $url }}">{{ $site->pageLabel($key) }}</a></li>
                        @endif
                    @endforeach
                </ul>
            </nav>
            <div class="text-sm">
                <p class="font-semibold text-white">Stay safe</p>
                <p class="mt-3 text-sand-300">
                    We will never ask for your card details by WhatsApp, SMS or chat.
                    @if ($url = $site->pageUrl('communication-policy'))
                        <a class="underline underline-offset-4 hover:text-white" href="{{ $url }}">How to spot a fake message</a>.
                    @endif
                </p>
                <p class="mt-6"><a href="{{ url('/staff') }}" class="text-sand-400 underline-offset-4 hover:text-white hover:underline">Staff sign-in</a></p>
            </div>
        </div>
        <p class="border-t border-lodge-800 py-4 text-center text-xs text-sand-400">&copy; {{ now()->year }} {{ $siteName }}</p>
    </footer>

    @stack('scripts')
</body>
</html>
