@php($site = app(\App\Support\SiteContent::class))
<x-layouts.site title="Book Accommodation" :noindex="true">
    <x-site.page-header title="Book your stay" :summary="$type->name.' · '.$stay->arrival->format('D j M').' – '.$stay->departure->format('D j M Y')" />

    <form method="POST" action="{{ route('book.store') }}" class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[3fr_2fr]" novalidate>
        @csrf
        <x-site.honeypot />
        <input type="hidden" name="accommodation_type_id" value="{{ $type->id }}">
        <input type="hidden" name="arrival" value="{{ $stay->arrivalString() }}">
        <input type="hidden" name="departure" value="{{ $stay->departureString() }}">
        <input type="hidden" name="adults" value="{{ $adults }}">
        <input type="hidden" name="children" value="{{ $children }}">

        <div class="space-y-10">
            @unless ($isAvailable)
                <div class="rounded-2xl bg-red-50 p-5 text-sm text-red-800 ring-1 ring-red-200" role="alert">
                    Sorry, this room type has just been taken for these dates.
                    <a class="font-semibold underline" href="{{ route('availability', ['arrival' => $stay->arrivalString(), 'departure' => $stay->departureString(), 'adults' => $adults, 'children' => $children]) }}">See what else is free</a>.
                </div>
            @endunless

            @if ($errors->any())
                <div class="rounded-2xl bg-red-50 p-5 text-sm text-red-800 ring-1 ring-red-200" role="alert">
                    <p class="font-semibold">Please check the form:</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if ($extras->isNotEmpty())
                <fieldset>
                    <legend class="font-display text-2xl font-semibold text-lodge-900">Add to your stay</legend>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($extras as $extra)
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-sand-200 bg-white p-4 has-[:checked]:border-lodge-700 has-[:checked]:ring-2 has-[:checked]:ring-lodge-700/20">
                                <input type="checkbox" name="extras[]" value="{{ $extra->id }}" @checked(in_array($extra->id, old('extras', []))) class="mt-1 size-4 rounded border-sand-300 text-lodge-800">
                                <span>
                                    <span class="flex items-center gap-2 font-semibold text-ink-900">{{ $extra->name }} @if ($extra->is_demo)<x-site.demo-badge />@endif</span>
                                    <span class="block text-sm text-ink-900/70">{{ $extra->price_cents->format() }} {{ $extra->pricing_unit->label() }}</span>
                                    @if ($extra->description)<span class="mt-1 block text-xs text-ink-900/60">{{ $extra->description }}</span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <fieldset class="space-y-5">
                <legend class="font-display text-2xl font-semibold text-lodge-900">Your details</legend>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-site.field name="first_name" label="First name" required autocomplete="given-name" />
                    <x-site.field name="last_name" label="Surname" required autocomplete="family-name" />
                    <x-site.field name="email" label="Email" type="email" required autocomplete="email" hint="We send your invoice and confirmation here." />
                    <x-site.field name="phone" label="Mobile number" type="tel" required autocomplete="tel" hint="Used for your booking and the in-room guest portal code." />
                </div>
                <x-site.field name="special_requests" label="Special requests (optional)" type="textarea" />
            </fieldset>

            <fieldset>
                <legend class="font-display text-2xl font-semibold text-lodge-900">How would you like to pay?</legend>
                <div class="mt-4 space-y-3">
                    @foreach ($methods as $index => $method)
                        @php($disabled = $method === \App\Enums\PaymentMethod::Eft && ! $bankDetailsAvailable)
                        <label @class([
                            'flex items-start gap-3 rounded-2xl border border-sand-200 bg-white p-4 has-[:checked]:border-lodge-700 has-[:checked]:ring-2 has-[:checked]:ring-lodge-700/20',
                            'cursor-pointer' => ! $disabled,
                            'opacity-60' => $disabled,
                        ])>
                            <input type="radio" name="payment_method" value="{{ $method->value }}" required @disabled($disabled)
                                @checked(old('payment_method', $index === 0 ? $method->value : null) === $method->value) class="mt-1 size-4 border-sand-300 text-lodge-800">
                            <span>
                                <span class="font-semibold text-ink-900">{{ $method->label() }}</span>
                                @if ($index === 0)<span class="ml-2 rounded-full bg-lodge-800/10 px-2 py-0.5 text-xs font-semibold text-lodge-800">Recommended</span>@endif
                                <span class="block text-sm text-ink-900/70">{{ $disabled ? 'Not available yet.' : $method->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-3 text-sm text-ink-900/70">Your room is held for {{ $holdMinutes }} minutes while you pay. Card details are only ever entered on our payment provider's secure page.</p>
            </fieldset>

            <div class="space-y-3">
                <label class="flex items-start gap-3 text-sm text-ink-900/85">
                    <input type="checkbox" name="accept_terms" value="1" required @checked(old('accept_terms')) class="mt-1 size-4 rounded border-sand-300 text-lodge-800"
                        @error('accept_terms') aria-invalid="true" aria-describedby="accept-terms-error" @enderror>
                    <span>
                        I have read the
                        @if ($url = $site->pageUrl('policies'))<a class="font-medium text-lodge-700 underline" href="{{ $url }}" target="_blank">booking and cancellation policies</a>@else booking and cancellation policies @endif
                        @if ($url = $site->pageUrl('house-rules')) and <a class="font-medium text-lodge-700 underline" href="{{ $url }}" target="_blank">house rules</a>@endif.
                    </span>
                </label>
                @error('accept_terms')<p id="accept-terms-error" class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                <label class="flex items-start gap-3 text-sm text-ink-900/80">
                    <input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent')) class="mt-1 size-4 rounded border-sand-300 text-lodge-800">
                    <span>Optional: send me occasional news and specials.</span>
                </label>
            </div>

            <button type="submit" @disabled(! $isAvailable) class="w-full rounded-full bg-lodge-800 px-6 py-3.5 font-semibold text-white transition hover:bg-lodge-700 disabled:opacity-50 sm:w-auto">
                Continue to payment
            </button>
        </div>

        <aside class="h-fit rounded-2xl border border-sand-200 bg-white p-6 shadow-sm lg:sticky lg:top-24" aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="font-display text-xl font-semibold text-lodge-900">Your stay</h2>
            <p class="mt-1 text-sm text-ink-900/70">{{ $type->name }} · {{ $adults }} {{ \Illuminate\Support\Str::plural('adult', $adults) }}@if ($children), {{ $children }} {{ \Illuminate\Support\Str::plural('child', $children) }}@endif</p>
            <table class="mt-4 w-full text-sm">
                <caption class="sr-only">Nightly rates</caption>
                <tbody class="divide-y divide-sand-200">
                    @foreach ($quote->nightlyRates as $date => $rate)
                        <tr>
                            <th scope="row" class="py-2 text-left font-normal text-ink-900/80">{{ \Carbon\CarbonImmutable::parse($date)->format('D j M') }}</th>
                            <td class="py-2 text-right">{{ $rate->format() }}</td>
                        </tr>
                    @endforeach
                    @if ($quote->extraGuests > 0)
                        <tr>
                            <th scope="row" class="py-2 text-left font-normal text-ink-900/80">{{ $quote->extraGuests }} extra {{ \Illuminate\Support\Str::plural('guest', $quote->extraGuests) }}</th>
                            <td class="py-2 text-right">{{ $quote->extraGuestTotal()->format() }}</td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-ink-900/20">
                        <th scope="row" class="pt-3 text-left font-semibold">Room total</th>
                        <td class="pt-3 text-right text-lg font-semibold text-lodge-900">{{ $quote->total()->format() }}</td>
                    </tr>
                </tfoot>
            </table>
            <p class="mt-3 text-xs text-ink-900/60">
                Extras you choose are added on the next page.
                @if ($depositPercent < 100) A {{ $depositPercent }}% payment confirms your booking. @else Full payment confirms your booking. @endif
            </p>
        </aside>
    </form>
</x-layouts.site>
