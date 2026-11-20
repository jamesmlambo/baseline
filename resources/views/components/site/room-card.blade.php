@props(['type', 'price' => null, 'priceLabel' => 'from', 'priceSuffix' => 'per night', 'href' => null])
@php
    $media = $type->getFirstMedia('gallery');
@endphp
<article class="group relative flex flex-col overflow-hidden rounded-2xl border border-sand-200 bg-white shadow-sm transition hover:shadow-md">
    <div class="relative aspect-[4/3] bg-gradient-to-br from-lodge-800 to-lodge-700">
        @if ($media)
            <img src="{{ $type->imageUrl('gallery', 'card') }}"
                 @if ($media->hasGeneratedConversion('card')) srcset="{{ $media->getSrcset('card') }}" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" @endif
                 alt="{{ $media->getCustomProperty('alt', $type->name) }}"
                 loading="lazy" decoding="async" class="size-full object-cover">
        @else
            <div class="flex size-full items-center justify-center text-sm text-white/70">Photo coming soon</div>
        @endif
        @if ($type->is_demo)
            <span class="absolute left-3 top-3"><x-site.demo-badge /></span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-3 p-5">
        <h3 class="font-display text-xl font-semibold text-lodge-900">
            <a href="{{ $href ?? route('rooms.show', $type->slug) }}" class="after:absolute after:inset-0 focus-visible:outline-2 focus-visible:outline-lodge-700">{{ $type->name }}</a>
        </h3>
        @if ($type->summary)
            <p class="text-sm text-ink-900/70">{{ $type->summary }}</p>
        @endif
        <p class="text-sm text-ink-900/70">Sleeps up to {{ $type->max_occupancy }}</p>
        <div class="mt-auto">{{ $slot }}</div>
        @if ($price)
            <p class="text-sm">
                <span class="text-ink-900/60">{{ $priceLabel }}</span>
                <span class="text-lg font-semibold text-lodge-900">{{ $price->format() }}</span>
                <span class="text-ink-900/60">{{ $priceSuffix }}</span>
            </p>
        @endif
    </div>
</article>
