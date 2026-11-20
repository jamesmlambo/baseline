@php
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $groups->flatten()->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq->question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($faq->answer)],
        ])->values()->all(),
    ];
@endphp
<x-layouts.site title="Frequently Asked Questions" description="Answers to common questions about staying at Baseline Lodge.">
    @if ($groups->isNotEmpty())
        @push('head')
            <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endpush
    @endif

    <x-site.page-header title="Frequently Asked Questions" />

    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        @forelse ($groups as $category => $faqs)
            <h2 class="mt-10 font-display text-2xl font-semibold text-lodge-900 first:mt-0">{{ $category }}</h2>
            <div class="mt-4 divide-y divide-sand-200 rounded-2xl border border-sand-200 bg-white">
                @foreach ($faqs as $faq)
                    <details class="group p-5">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-4 font-semibold text-ink-900 [&::-webkit-details-marker]:hidden">
                            <span>{{ $faq->question }} @if ($faq->is_demo) <x-site.demo-badge /> @endif</span>
                            <span class="mt-0.5 text-lodge-700 transition group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <div class="mt-3 text-ink-900/80">{!! nl2br(e($faq->answer)) !!}</div>
                    </details>
                @endforeach
            </div>
        @empty
            <p class="text-ink-900/70">No questions yet. <a class="font-medium text-lodge-700 underline" href="{{ route('contact') }}">Ask us anything</a>.</p>
        @endforelse
    </div>
</x-layouts.site>
