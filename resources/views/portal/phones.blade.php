<x-layouts.portal title="Other guests" :booking="$booking">
    <h1 class="font-display text-2xl font-semibold text-lodge-900">Other guests in your room</h1>

    @if (! $isBooker)
        <p class="mt-4 rounded-2xl bg-white p-5 text-ink-900/75 shadow-sm ring-1 ring-sand-200">Only the person who made the booking can add other guests. Ask them, or ask reception.</p>
    @else
        <p class="mt-2 text-ink-900/75">Add the mobile number of someone staying with you so they can sign in with the room QR code too. You can remove them at any time.</p>

        <ul class="mt-4 space-y-2">
            @forelse ($phones as $phone)
                <li class="flex items-center justify-between gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-sand-200">
                    <span>{{ $phone->label ?: 'Guest' }} · <span class="font-mono">{{ $phone->maskedPhone() }}</span></span>
                    <form method="POST" action="{{ route('portal.phones.remove', $phone->id) }}">
                        @csrf
                        <button class="text-sm font-semibold text-red-700 underline">Remove</button>
                    </form>
                </li>
            @empty
                <li class="text-sm text-ink-900/60">Nobody added yet.</li>
            @endforelse
        </ul>

        <form method="POST" action="{{ route('portal.phones.add') }}" class="mt-6 space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sand-200" novalidate>
            @csrf
            <x-site.field name="label" label="Name (optional)" />
            <x-site.field name="phone" label="Mobile number" type="tel" required />
            <button class="w-full rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Add guest</button>
        </form>
    @endif
</x-layouts.portal>
