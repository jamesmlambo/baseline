@props(['title', 'summary' => null, 'image' => null, 'eyebrow' => null, 'demo' => false])
<section class="relative overflow-hidden bg-lodge-800 text-white">
    @if ($image)
        <img src="{{ $image }}" alt="" class="absolute inset-0 size-full object-cover opacity-40" fetchpriority="high">
    @else
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(234,179,8,0.22),transparent_45%),radial-gradient(circle_at_85%_80%,rgba(20,184,166,0.3),transparent_50%)]" aria-hidden="true"></div>
    @endif
    <div class="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
        @if ($eyebrow)
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-2 flex flex-wrap items-center gap-3 font-display text-3xl font-semibold sm:text-5xl">
            {{ $title }}
            @if ($demo) <x-site.demo-badge class="text-xs" /> @endif
        </h1>
        @if ($summary)
            <p class="mt-4 max-w-2xl text-lg text-sand-100">{{ $summary }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
