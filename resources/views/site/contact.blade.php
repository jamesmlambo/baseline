@php
    $site = app(\App\Support\SiteContent::class);
    $contact = $site->contact();
    $whatsApp = $site->whatsAppUrl('Hello Baseline Lodge');
@endphp
<x-layouts.site :title="$page?->title ?? 'Contact Us'" :description="$page?->meta_description ?? 'Get in touch with Baseline Lodge.'">
    <x-site.page-header :title="$page?->title ?? 'Contact Us'" :summary="$page?->summary" />

    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[3fr_2fr]">
        <section aria-labelledby="form-heading">
            <h2 id="form-heading" class="font-display text-2xl font-semibold text-lodge-900">Send us a message</h2>
            @if ($page?->body)
                <div class="cms-content mt-3">{!! \App\Support\SafeHtml::clean($page->body) !!}</div>
            @endif

            <form method="POST" action="{{ route('contact.store') }}" class="relative mt-6 space-y-5" novalidate>
                @csrf
                <x-site.honeypot />
                <x-site.field name="name" label="Your name" required autocomplete="name" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-site.field name="email" label="Email" type="email" autocomplete="email" />
                    <x-site.field name="phone" label="Phone" type="tel" autocomplete="tel" />
                </div>
                <p class="-mt-2 text-xs text-ink-900/60">Give us an email address, a phone number, or both.</p>
                <x-site.field name="message" label="Message" type="textarea" required />
                <label class="flex items-start gap-3 text-sm text-ink-900/80">
                    <input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent')) class="mt-1 size-4 rounded border-sand-300 text-lodge-800">
                    <span>Optional: send me occasional news and specials. You can unsubscribe at any time.</span>
                </label>
                <button type="submit" class="rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white transition hover:bg-lodge-700">Send message</button>
            </form>
        </section>

        <aside class="space-y-6">
            <div class="rounded-2xl border border-sand-200 bg-white p-6">
                <h2 class="font-display text-xl font-semibold text-lodge-900">Reach us</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    @if ($contact['phone'])<div><dt class="font-semibold">Phone</dt><dd><a class="text-lodge-700 underline" href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></dd></div>@endif
                    @if ($contact['email'])<div><dt class="font-semibold">Email</dt><dd><a class="text-lodge-700 underline" href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd></div>@endif
                    @if ($contact['address'])<div><dt class="font-semibold">Address</dt><dd>{{ $contact['address'] }}</dd></div>@endif
                    @unless ($contact['phone'] || $contact['email'] || $contact['address'])
                        <div><dd class="text-ink-900/70">Phone, email and address will appear here once supplied.</dd></div>
                    @endunless
                </dl>
                @if ($whatsApp)
                    <a href="{{ $whatsApp }}" target="_blank" rel="noopener" class="mt-5 inline-flex rounded-full bg-[#1f8f4e] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#187a41]">Chat on WhatsApp</a>
                @endif
            </div>
            <div class="rounded-2xl bg-amber-50 p-6 text-sm text-ink-900/80 ring-1 ring-amber-200">
                <p class="font-semibold text-ink-900">Stay safe</p>
                <p class="mt-1">We will never ask for your card details by WhatsApp, SMS or chat.</p>
            </div>
        </aside>
    </div>
</x-layouts.site>
