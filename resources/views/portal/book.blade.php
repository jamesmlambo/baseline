<x-layouts.portal title="Book" :booking="$booking">
    <h1 class="font-display text-2xl font-semibold text-lodge-900">Book an experience</h1>

    @error('cancel')<p class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ $message }}</p>@enderror

    @if ($services->isEmpty())
        <p class="mt-4 rounded-2xl bg-white p-5 text-ink-900/70 shadow-sm ring-1 ring-sand-200">Nothing can be booked here right now. <a class="font-medium text-lodge-700 underline" href="{{ route('portal.messages') }}">Ask reception</a>.</p>
    @else
        <form method="GET" action="{{ route('portal.book') }}" class="mt-4 space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sand-200">
            <div>
                <label for="service" class="block text-sm font-semibold">What would you like?</label>
                <select id="service" name="service" class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-2" onchange="this.form.submit()">
                    <option value="">Choose…</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected($selected?->id === $service->id)>{{ $service->name }} — {{ $service->priceLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="date" class="block text-sm font-semibold">Date</label>
                    <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" min="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-2">
                </div>
                <div>
                    <label for="people" class="block text-sm font-semibold">People</label>
                    <input id="people" type="number" name="people" min="1" max="20" value="{{ $people }}" class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-2">
                </div>
            </div>
            <button class="w-full rounded-full border border-lodge-800 px-6 py-2.5 font-semibold text-lodge-900 hover:bg-lodge-800/5">Show times</button>
        </form>

        @if ($selected)
            <section class="mt-6" aria-labelledby="times-heading">
                <h2 id="times-heading" class="font-display text-lg font-semibold text-lodge-900">{{ $selected->name }} @if ($selected->is_demo)<x-site.demo-badge />@endif</h2>
                @if ($selected->description)<p class="mt-1 text-sm text-ink-900/70">{{ $selected->description }}</p>@endif
                @if ($selected->restrictions)<p class="mt-1 text-sm text-amber-800">{{ $selected->restrictions }}</p>@endif
                <p class="mt-1 text-sm text-ink-900/70">{{ $selected->duration_minutes }} minutes · {{ $selected->priceLabel() }} · {{ $selected->charge_to_room_allowed ? 'added to your room bill' : 'pay at reception' }}</p>

                @if ($slots === [])
                    <p class="mt-3 rounded-xl bg-sand-100 p-4 text-sm">No free times on {{ $date->format('l j F') }}. Try another day.</p>
                @else
                    <form method="POST" action="{{ route('portal.book.store') }}" class="mt-3 space-y-4" novalidate>
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $selected->id }}">
                        <input type="hidden" name="people" value="{{ $people }}">
                        @error('start')<p class="rounded-xl bg-red-50 p-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ $message }}</p>@enderror
                        <fieldset>
                            <legend class="text-sm font-semibold">Choose a time on {{ $date->format('l j F') }}</legend>
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                @foreach ($slots as $slot)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="start" value="{{ $slot->toIso8601String() }}" class="peer sr-only" required @checked(old('start') === $slot->toIso8601String())>
                                        <span class="block rounded-lg border border-sand-300 bg-white px-2 py-2 text-center text-sm font-medium peer-checked:border-lodge-800 peer-checked:bg-lodge-800 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-lodge-700">{{ $slot->setTimezone('Africa/Johannesburg')->format('H:i') }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <x-site.field name="notes" label="Anything we should know? (optional)" type="textarea" />
                        @if ($needsIndemnity)
                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" name="indemnity" value="1" required class="mt-1 size-4 rounded border-sand-300 text-lodge-800">
                                <span>I have read and accept the safety notice for this activity.</span>
                            </label>
                        @endif
                        <p class="text-sm">Total: <strong>{{ $selected->priceFor($people)->format() }}</strong>. You can cancel here up to {{ $selected->cancellation_cutoff_hours }} hours before.</p>
                        <button class="w-full rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Book</button>
                    </form>
                @endif
            </section>
        @endif
    @endif

    @if ($myBookings->isNotEmpty())
        <section class="mt-8" aria-labelledby="mine-heading">
            <h2 id="mine-heading" class="font-display text-lg font-semibold text-lodge-900">Your bookings</h2>
            <ul class="mt-2 divide-y divide-sand-200 rounded-2xl bg-white shadow-sm ring-1 ring-sand-200">
                @foreach ($myBookings as $item)
                    <li class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <p class="font-semibold">{{ $item->service->name }}</p>
                            <p class="text-sm text-ink-900/70">{{ $item->starts_at->setTimezone('Africa/Johannesburg')->format('D j M, H:i') }} · {{ $item->party_size }} {{ $item->party_size === 1 ? 'person' : 'people' }} · {{ $item->status->label() }}</p>
                        </div>
                        @if ($item->status === \App\Enums\ServiceBookingStatus::Confirmed && $item->starts_at->subHours($item->service->cancellation_cutoff_hours)->isFuture())
                            <form method="POST" action="{{ route('portal.book.cancel', $item->id) }}" onsubmit="return confirm('Cancel this booking?');">
                                @csrf
                                <button class="text-sm font-semibold text-red-700 underline">Cancel</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.portal>
