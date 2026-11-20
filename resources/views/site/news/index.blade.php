<x-layouts.site title="News & Events" description="News, events and specials from Baseline Lodge.">
    <x-site.page-header title="News & Events" summary="What's happening at the lodge." />

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if ($posts->isEmpty())
            <p class="text-ink-900/70">No news yet. Check back soon.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-site.post-card :post="$post" />
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </section>
</x-layouts.site>
