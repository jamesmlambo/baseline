@php($site = app(\App\Support\SiteContent::class))
<x-layouts.portal title="Your stay" :booking="$booking">
    <h1 class="font-display text-2xl font-semibold text-lodge-900">Welcome, {{ $booking->guest?->first_name ?? strtok($booking->lead_guest_name, ' ') }}</h1>
    @if ($info['welcome'])
        <p class="mt-2 text-ink-900/80">{!! nl2br(e($info['welcome'])) !!}</p>
    @endif

    <div class="mt-6 grid grid-cols-2 gap-3">
        <a href="{{ route('portal.requests') }}" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-sand-200 hover:ring-lodge-700">
            <span class="block font-semibold">Towels &amp; help</span>
            <span class="text-xs text-ink-900/60">{{ $openRequests > 0 ? $openRequests.' open' : 'Ask reception' }}</span>
        </a>
        <a href="{{ route('portal.menu') }}" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-sand-200 hover:ring-lodge-700">
            <span class="block font-semibold">Order food</span>
            <span class="text-xs text-ink-900/60">To your room</span>
        </a>
        <a href="{{ route('portal.messages') }}" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-sand-200 hover:ring-lodge-700">
            <span class="block font-semibold">Message reception</span>
            <span class="text-xs text-ink-900/60">{{ $unreadReplies > 0 ? $unreadReplies.' new reply' : 'We reply here' }}</span>
        </a>
        <a href="{{ route('portal.bill') }}" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-sand-200 hover:ring-lodge-700">
            <span class="block font-semibold">Bill &amp; checkout</span>
            <span class="text-xs text-ink-900/60">Owing {{ $booking->balanceDue()->cents > 0 ? $booking->balanceDue()->format() : 'nothing' }}</span>
        </a>
    </div>

    <dl class="mt-6 space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sand-200">
        @if ($info['wifi_name'])
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-900/60">Wi-Fi</dt>
                <dd class="mt-1">{{ $info['wifi_name'] }}@if ($info['wifi_password']) · password <span class="font-mono font-semibold">{{ $info['wifi_password'] }}</span>@endif</dd>
            </div>
        @endif
        @if ($info['check_out_time'])
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-900/60">Check-out</dt>
                <dd class="mt-1">{{ $booking->departure_date->format('l j F') }} by {{ $info['check_out_time'] }}</dd>
            </div>
        @else
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-900/60">Departure</dt>
                <dd class="mt-1">{{ $booking->departure_date->format('l j F') }}</dd>
            </div>
        @endif
        @if ($info['facility_hours'])
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-900/60">Opening hours</dt>
                <dd class="mt-1">{!! nl2br(e($info['facility_hours'])) !!}</dd>
            </div>
        @endif
        @if ($info['emergency'])
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-red-700">Emergencies</dt>
                <dd class="mt-1">{!! nl2br(e($info['emergency'])) !!}</dd>
            </div>
        @endif
        @if ($url = $site->pageUrl('house-rules'))
            <div><dd><a class="font-medium text-lodge-700 underline" href="{{ $url }}">House rules</a></dd></div>
        @endif
    </dl>

    <ul class="mt-6 space-y-2 text-sm">
        <li><a class="font-medium text-lodge-700 underline" href="{{ route('portal.review') }}">Tell us about your stay</a></li>
        <li><a class="font-medium text-lodge-700 underline" href="{{ route('portal.phones') }}">Other guests in your room</a></li>
    </ul>
</x-layouts.portal>
