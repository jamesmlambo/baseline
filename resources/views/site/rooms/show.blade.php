@php
    $gallery = $type->getMedia('gallery');
    $hero = $gallery->first();
    $roomSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'HotelRoom',
        'name' => $type->name,
        'description' => $type->summary,
        'url' => route('rooms.show', $type->slug),
        'occupancy' => ['@type' => 'QuantitativeValue', 'maxValue' => $type->max_occupancy],
        'amenityFeature' => $type->amenities->map(fn ($amenity) => ['@type' => 'LocationFeatureSpecification', 'name' => $amenity->name, 'value' => true])->all() ?: null,
        'image' => $hero ? $type->imageUrl('gallery', 'hero') : null,
    ]);
@endphp
<x-layouts.site :title="$type->name" :description="$type->summary" :image="$hero ? $type->imageUrl('gallery', 'hero') : null">
    @push('head')
        <script type="application/ld+json">{!! json_encode($roomSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endpush

    <x-site.page-header :title="$type->name" :summary="$type->summary" eyebrow="Room" :demo="$type->is_demo"
        :image="$hero ? $type->imageUrl('gallery', 'hero') : null" />

    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[2fr_1fr]">
        <div>
            @if ($gallery->count() > 1)
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label="Photos">
                    @foreach ($gallery as $media)
                        <li>
                            <img src="{{ $media->hasGeneratedConversion('card') ? $media->getUrl('card') : $media->getUrl() }}"
                                 alt="{{ $media->getCustomProperty('alt', $type->name) }}" loading="lazy" decoding="async"
                                 class="aspect-[4/3] w-full rounded-xl object-cover">
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($type->description)
                <div class="cms-content mt-8">{!! nl2br(e($type->description)) !!}</div>
            @endif

            @if ($type->amenities->isNotEmpty())
                <h2 class="mt-10 font-display text-2xl font-semibold text-lodge-900">In the room</h2>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($type->amenities as $amenity)
                        <li class="flex items-center gap-2.5 text-ink-900/80">
                            {{ $amenity->iconSvg('size-6 shrink-0 text-lodge-700') }}
                            {{ $amenity->name }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <aside class="h-fit rounded-2xl border border-sand-200 bg-white p-6 shadow-sm lg:sticky lg:top-24" aria-labelledby="book-heading">
            <h2 id="book-heading" class="font-display text-xl font-semibold text-lodge-900">Check your dates</h2>
            <p class="mt-2 text-sm text-ink-900/70">
                From <span class="font-semibold text-ink-900">{{ $fromPrice->format() }}</span> per night ·
                sleeps up to {{ $type->max_occupancy }}
                @if ($type->max_occupancy > $type->base_occupancy && ! $type->extra_guest_nightly_cents->isZero())
                    · rate includes {{ $type->base_occupancy }} guests, extra guests {{ $type->extra_guest_nightly_cents->format() }} per night
                @endif
            </p>
            <x-site.search-form class="mt-4 !grid-cols-1 !shadow-none !ring-0 !p-0" />
        </aside>
    </div>
</x-layouts.site>
