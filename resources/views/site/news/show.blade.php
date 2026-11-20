@php
    $cover = $post->imageUrl('cover', 'hero');
    $eventSchema = $post->event_starts_at ? array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $post->title,
        'startDate' => $post->event_starts_at->toIso8601String(),
        'endDate' => $post->event_ends_at?->toIso8601String(),
        'description' => $post->excerpt,
        'location' => ['@type' => 'Place', 'name' => $post->event_location ?? config('app.name')],
        'image' => $cover,
    ]) : null;
@endphp
<x-layouts.site :title="$post->meta_title ?? $post->title" :description="$post->meta_description ?? $post->excerpt" :image="$cover">
    @if ($eventSchema)
        @push('head')
            <script type="application/ld+json">{!! json_encode($eventSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endpush
    @endif

    <x-site.page-header :title="$post->title" :summary="$post->excerpt" :eyebrow="$post->type->label()" :demo="$post->is_demo" :image="$cover" />

    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <p class="text-sm text-ink-900/60">
            Published <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('j F Y') }}</time>
        </p>
        @if ($post->event_starts_at)
            <dl class="mt-6 grid gap-2 rounded-2xl bg-sand-100 p-5 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold">When</dt><dd>{{ $post->event_starts_at->format('l j F Y, H:i') }}@if ($post->event_ends_at) – {{ $post->event_ends_at->format($post->event_ends_at->isSameDay($post->event_starts_at) ? 'H:i' : 'l j F Y, H:i') }}@endif</dd></div>
                @if ($post->event_location)<div><dt class="font-semibold">Where</dt><dd>{{ $post->event_location }}</dd></div>@endif
            </dl>
        @endif
        <div class="cms-content mt-8">{!! \App\Support\SafeHtml::clean($post->body) !!}</div>
        <p class="mt-12"><a href="{{ route('news.index') }}" class="font-semibold text-lodge-700 hover:text-lodge-900">← All news &amp; events</a></p>
    </article>
</x-layouts.site>
