@php($site = app(\App\Support\SiteContent::class))
<x-layouts.site title="Book Accommodation" :noindex="true">
    <x-site.page-header title="Request a booking" :summary="$type->name.' · '.$stay->arrival->format('D j M').' – '.$stay->departure->format('D j M Y')" />

    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[3fr_2fr]">
        <section aria-labelledby="details-heading">
            <div class="rounded-2xl bg-amber-50 p-5 text-sm text-ink-900/85 ring-1 ring-amber-200" role="note">
                <p class="font-semibold text-ink-900">Your room is not reserved yet.</p>
                <p class="mt-1">Online booking and payment are coming soon. For now, send this request and reception will contact you to confirm availability, the final price and payment.</p>
            </div>

            @unless ($isAvailable)
                <div class="mt-4 rounded-2xl bg-red-50 p-5 text-sm text-red-800 ring-1 ring-red-200" role="alert">
                    This room type has just been taken for these dates. You can still send a request and we'll suggest alternatives.
                </div>
            @endunless

            <h2 id="details-heading" class="mt-8 font-display text-2xl font-semibold text-lodge-900">Your details</h2>
            <form method="POST" action="{{ route('book.store') }}" class="relative mt-6 space-y-5" novalidate>
                @csrf
                <x-site.honeypot />
                <input type="hidden" name="accommodation_type_id" value="{{ $type->id }}">
                <input type="hidden" name="arrival" value="{{ $stay->arrivalString() }}">
                <input type="hidden" name="departure" value="{{ $stay->departureString() }}">
                <input type="hidden" name="adults" value="{{ $adults }}">
                <input type="hidden" name="children" value="{{ $children }}">

                <x-site.field name="name" label="Name and surname" required autocomplete="name" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-site.field name="phone" label="Phone" type="tel" autocomplete="tel" />
                    <x-site.field name="email" label="Email" type="email" autocomplete="email" />
                </div>
                <p class="-mt-2 text-xs text-ink-900/60">Give us a phone number, an email address, or both.</p>
                <x-site.field name="message" label="Special requests (optional)" type="textarea" />
                @error('adults')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror

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

                <button type="submit" class="rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white transition hover:bg-lodge-700">Send booking request</button>
            </form>
        </section>

        <aside class="h-fit rounded-2xl border border-sand-200 bg-white p-6 shadow-sm" aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="font-display text-xl font-semibold text-lodge-900">Price summary</h2>
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
                        <th scope="row" class="pt-3 text-left font-semibold">Estimated total</th>
                        <td class="pt-3 text-right text-lg font-semibold text-lodge-900">{{ $quote->total()->format() }}</td>
                    </tr>
                </tfoot>
            </table>
            <p class="mt-3 text-xs text-ink-900/60">Taxes, fees, extras and deposit requirements will be confirmed by reception.</p>
        </aside>
    </div>
</x-layouts.site>
