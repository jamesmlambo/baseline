<x-layouts.portal title="Requests" :booking="$booking">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sand-200">
        <h1 class="font-display text-xl font-semibold text-lodge-900">Ask for something</h1>
        <form method="POST" action="{{ route('portal.requests.store') }}" class="mt-4 space-y-4" novalidate>
            @csrf
            <fieldset>
                <legend class="text-sm font-semibold">What do you need?</legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach ($types as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-sand-200 p-3 has-[:checked]:border-lodge-700 has-[:checked]:bg-lodge-800/5">
                            <input type="radio" name="type" value="{{ $value }}" required @checked(old('type', 'towels') === $value) class="size-4 text-lodge-800">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('type')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </fieldset>
            <x-site.field name="details" label="Details (optional)" type="textarea" />
            <button class="w-full rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Send to reception</button>
        </form>
    </section>

    <h2 class="mt-8 font-display text-lg font-semibold text-lodge-900">Your requests</h2>
    <ul class="mt-3 space-y-3">
        @forelse ($requests as $guestRequest)
            <li class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-sand-200">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-semibold">{{ $guestRequest->type->label() }}</p>
                    <span @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-amber-100 text-amber-900' => $guestRequest->status === \App\Enums\GuestRequestStatus::New,
                        'bg-sky-100 text-sky-900' => $guestRequest->status === \App\Enums\GuestRequestStatus::Acknowledged,
                        'bg-emerald-100 text-emerald-900' => $guestRequest->status === \App\Enums\GuestRequestStatus::Done,
                        'bg-sand-100 text-ink-900/70' => $guestRequest->status === \App\Enums\GuestRequestStatus::Cancelled,
                    ])>{{ $guestRequest->status->label() }}</span>
                </div>
                @if ($guestRequest->items)
                    <p class="mt-1 text-sm text-ink-900/75">{{ collect($guestRequest->items)->map(fn ($item) => $item['quantity'].' × '.$item['name'])->implode(', ') }} · {{ $guestRequest->total_cents->format() }}</p>
                @endif
                @if ($guestRequest->details)<p class="mt-1 text-sm text-ink-900/75">{{ $guestRequest->details }}</p>@endif
                <p class="mt-1 text-xs text-ink-900/50">{{ $guestRequest->created_at->setTimezone('Africa/Johannesburg')->format('D H:i') }}</p>
            </li>
        @empty
            <li class="text-sm text-ink-900/60">Nothing yet.</li>
        @endforelse
    </ul>
</x-layouts.portal>
