<!DOCTYPE html>
<html lang="en-ZA" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <meta name="description" content="{{ $description ?? config('app.name') }}">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-sand-50 font-sans text-ink-900 antialiased flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:shadow">
        Skip to content
    </a>

    @unless (app()->isProduction())
        <div class="bg-amber-400 px-4 py-1.5 text-center text-xs font-semibold tracking-wide text-ink-900" role="status">
            DEMO — content, prices and photos are placeholders. Replace before launch.
        </div>
    @endunless

    <header class="border-b border-sand-200 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/75 sticky top-0 z-40">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="font-display text-xl font-semibold tracking-tight text-lodge-800 sm:text-2xl">
                Baseline Lodge
            </a>
            <nav aria-label="Main" class="flex items-center gap-1 text-sm font-medium">
                <a href="{{ route('home') }}" @class([
                    'rounded-full px-3 py-2 transition hover:bg-sand-100',
                    'text-lodge-800' => request()->routeIs('home'),
                ]) @if (request()->routeIs('home')) aria-current="page" @endif>Home</a>
            </nav>
        </div>
    </header>

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-sand-200 bg-lodge-900 text-sand-100">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-3 sm:px-6">
            <div>
                <p class="font-display text-lg font-semibold">Baseline Lodge</p>
                <p class="mt-2 text-sm text-sand-300">Guesthouse in South Africa.</p>
            </div>
            <div class="text-sm">
                <p class="font-semibold">Official communication</p>
                <p class="mt-2 text-sand-300">
                    We will never ask for your card details by WhatsApp, SMS or chat.
                </p>
            </div>
            <div class="text-sm sm:text-right">
                <a href="{{ url('/staff') }}" class="text-sand-300 underline-offset-4 hover:text-white hover:underline">Staff sign-in</a>
            </div>
        </div>
        <p class="border-t border-lodge-800 py-4 text-center text-xs text-sand-400">
            &copy; {{ now()->year }} Baseline Lodge
        </p>
    </footer>
</body>
</html>
