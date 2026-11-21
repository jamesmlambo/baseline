@props(['post', 'headingLevel' => 3])
<article class="relative flex flex-col overflow-hidden rounded-2xl border border-sand-200 bg-white shadow-sm transition hover:shadow-md">
    @if ($url = $post->imageUrl('cover', 'card'))
        <img src="{{ $url }}" alt="" loading="lazy" decoding="async" class="aspect-[16/9] w-full object-cover">
    @endif
    <div class="flex flex-1 flex-col gap-2 p-5">
        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-lodge-700">
            {{ $post->type->label() }}
            @if ($post->is_demo) <x-site.demo-badge /> @endif
        </p>
        @php($headingTag = 'h'.max(2, min(6, (int) $headingLevel)))
        <{{ $headingTag }} class="font-display text-lg font-semibold text-lodge-900">
            <a href="{{ route('news.show', $post->slug) }}" class="after:absolute after:inset-0 focus-visible:outline-2 focus-visible:outline-lodge-700">{{ $post->title }}</a>
        </{{ $headingTag }}>
        @if ($post->event_starts_at)
            <p class="text-sm font-medium text-ink-900">
                <time datetime="{{ $post->event_starts_at->toIso8601String() }}">{{ $post->event_starts_at->format('l j F Y, H:i') }}</time>
            </p>
        @endif
        @if ($post->excerpt)
            <p class="text-sm text-ink-900/70">{{ $post->excerpt }}</p>
        @endif
        <p class="mt-auto pt-2 text-xs text-ink-900/70">
            <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('j M Y') }}</time>
        </p>
    </div>
</article>
