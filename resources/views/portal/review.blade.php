<x-layouts.portal title="Your review" :booking="$booking">
    <h1 class="font-display text-2xl font-semibold text-lodge-900">Tell us about your stay</h1>

    @if ($review)
        <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sand-200">
            <p class="font-semibold" aria-label="{{ $review->rating }} out of 5">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</p>
            @if ($review->comment)<p class="mt-2">{{ $review->comment }}</p>@endif
            <p class="mt-3 text-sm text-ink-900/60">Thank you! {{ $review->consent_to_publish ? 'We may publish it on our website after a quick check.' : 'It is for our team only.' }}</p>
        </div>
    @else
        <form method="POST" action="{{ route('portal.review.store') }}" enctype="multipart/form-data" class="mt-4 space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sand-200" novalidate>
            @csrf
            <fieldset>
                <legend class="text-sm font-semibold">Your rating</legend>
                <div class="mt-2 flex gap-2">
                    @foreach (range(1, 5) as $stars)
                        <label class="flex-1 cursor-pointer rounded-xl border border-sand-200 py-2 text-center has-[:checked]:border-lodge-700 has-[:checked]:bg-lodge-800/5">
                            <input type="radio" name="rating" value="{{ $stars }}" required class="sr-only" @checked((int) old('rating') === $stars)>
                            <span aria-hidden="true">{{ $stars }}★</span><span class="sr-only">{{ $stars }} out of 5</span>
                        </label>
                    @endforeach
                </div>
                @error('rating')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </fieldset>
            <x-site.field name="comment" label="Comments (optional)" type="textarea" />
            <x-site.field name="display_name" label="Name to show if published (optional)" hint="e.g. Thandi from Durban" />
            <div>
                <label for="photos" class="block text-sm font-semibold">Photos (optional, up to 3)</label>
                <input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 block w-full text-sm">
                <p class="mt-1 text-xs text-ink-900/60">Location data is removed from photos.</p>
                @error('photos.*')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="consent_to_publish" value="1" @checked(old('consent_to_publish')) class="mt-1 size-4 rounded border-sand-300 text-lodge-800">
                <span>You may publish my review (and photos) on the Baseline Lodge website after checking it.</span>
            </label>
            <button class="w-full rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Send review</button>
        </form>
    @endif
</x-layouts.portal>
