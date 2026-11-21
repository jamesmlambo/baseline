<x-layouts.portal title="Enter your code">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-sand-200">
        <h1 class="font-display text-2xl font-semibold text-lodge-900">Enter your code</h1>
        <p class="mt-2 text-ink-900/75">If that number is linked to this room's booking, we've sent it a 6-digit code. It works for 5 minutes.</p>

        <form method="POST" action="{{ route('portal.room.verify.submit', $token) }}" class="mt-6 space-y-4" novalidate>
            @csrf
            <div>
                <label for="code" class="block text-sm font-semibold">6-digit code</label>
                <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus
                    @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                    class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-3 text-center font-mono text-2xl tracking-[0.5em] focus:border-lodge-700 focus:outline-none focus:ring-2 focus:ring-lodge-700/30">
                @error('code')<p id="code-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Sign in</button>
        </form>
        <p class="mt-6 text-center text-sm"><a href="{{ route('portal.room', $token) }}" class="font-medium text-lodge-700 underline">Send a new code</a></p>
    </section>
</x-layouts.portal>
