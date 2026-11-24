@php
    $site = app(\App\Support\SiteContent::class);
    $hero = $page->imageUrl('hero', 'hero');
@endphp
<x-layouts.site :title="$page->meta_title ?? $page->title" :description="$page->meta_description ?? $page->summary" :image="$hero">
    <x-site.page-header :title="$page->title" :summary="$page->summary" :demo="$page->is_demo" :image="$hero" />

    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <div class="cms-content">{!! \App\Support\SafeHtml::clean($page->body) !!}</div>

        @if ($amenities->isNotEmpty())
            @foreach ($amenities as $category => $items)
                <h2 class="mt-12 font-display text-2xl font-semibold text-lodge-900">{{ $category }}</h2>
                <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($items as $amenity)
                        <li class="rounded-2xl border border-sand-200 bg-white p-5">
                            <p class="flex items-center gap-2.5 font-semibold text-ink-900">
                                {{ $amenity->iconSvg('size-6 shrink-0 text-lodge-700') }}
                                {{ $amenity->name }}
                                @if ($amenity->is_demo) <x-site.demo-badge /> @endif
                            </p>
                            @if ($amenity->description)
                                <p class="mt-1 text-sm text-ink-900/70">{{ $amenity->description }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif

        @if ($serviceKeys !== [])
            <ul class="mt-12 grid gap-4 sm:grid-cols-2">
                @foreach ($serviceKeys as $key)
                    @if ($service = $site->page($key))
                        <li>
                            <a href="{{ route('page', $service->slug) }}" class="block h-full rounded-2xl border border-sand-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                                <span class="font-display text-lg font-semibold text-lodge-900">{{ $service->title }}</span>
                                @if ($service->summary)
                                    <span class="mt-2 block text-sm text-ink-900/70">{{ $service->summary }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.site>
