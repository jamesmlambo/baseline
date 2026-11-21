@props(['title' => 'Guest portal', 'booking' => null])
@php
    $tabs = [
        ['route' => 'portal.home', 'label' => 'Home'],
        ['route' => 'portal.requests', 'label' => 'Requests'],
        ['route' => 'portal.menu', 'label' => 'Menu'],
        ['route' => 'portal.messages', 'label' => 'Reception'],
        ['route' => 'portal.bill', 'label' => 'Bill'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title }} · Baseline Lodge</title>
    <meta name="theme-color" content="#0f514b">
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-sand-50 font-sans text-ink-900 antialiased">
    <header class="bg-lodge-800 text-white">
        <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-4">
            <div>
                <p class="font-display text-lg font-semibold leading-tight">Baseline Lodge</p>
                @if ($booking)
                    <p class="text-xs text-sand-200">{{ $booking->activeBookingRooms->first()?->room?->name }} · until {{ $booking->departure_date->format('D j M') }}</p>
                @else
                    <p class="text-xs text-sand-200">Guest portal</p>
                @endif
            </div>
            @if ($booking)
                <form method="POST" action="{{ route('portal.sign-out') }}">
                    @csrf
                    <button class="rounded-full border border-white/30 px-3 py-1.5 text-xs font-semibold hover:bg-white/10">Sign out</button>
                </form>
            @endif
        </div>
        @if ($booking)
            <nav aria-label="Guest portal" class="mx-auto max-w-2xl overflow-x-auto px-2">
                <ul class="flex gap-1 pb-2 text-sm">
                    @foreach ($tabs as $tab)
                        @php($active = request()->routeIs($tab['route']))
                        <li>
                            <a href="{{ route($tab['route']) }}" @class([
                                'block whitespace-nowrap rounded-full px-3 py-1.5 font-medium',
                                'bg-white text-lodge-900' => $active,
                                'text-sand-100 hover:bg-white/10' => ! $active,
                            ]) @if ($active) aria-current="page" @endif>{{ $tab['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </header>

    @if (session('status'))
        <div class="border-b border-lodge-700/20 bg-lodge-800/5" role="status">
            <p class="mx-auto max-w-2xl px-4 py-3 text-sm font-medium text-lodge-900">{{ session('status') }}</p>
        </div>
    @endif

    <main class="mx-auto max-w-2xl px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-2xl px-4 pb-8 text-center text-xs text-ink-900/50">
        We will never ask for your card details by WhatsApp, SMS or chat.
    </footer>
</body>
</html>
