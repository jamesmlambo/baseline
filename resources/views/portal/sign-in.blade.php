<x-layouts.portal title="Sign in">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-sand-200">
        <h1 class="font-display text-2xl font-semibold text-lodge-900">Sign in to your room</h1>
        <p class="mt-2 text-ink-900/75">Enter the mobile number on your booking, or one the person who booked has added. We'll send you a 6-digit code.</p>

        <form method="POST" action="{{ route('portal.room.code', $token) }}" class="mt-6 space-y-4" novalidate>
            @csrf
            <x-site.field name="phone" label="Mobile number" type="tel" required autocomplete="tel" />
            <button type="submit" class="w-full rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Send me a code</button>
        </form>
        @if ($errors->has('phone') === false && $errors->any())
            <p class="mt-3 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif
        <p class="mt-6 text-xs text-ink-900/60">No phone signal or a different number? Reception can help after checking your ID.</p>
    </section>
</x-layouts.portal>
