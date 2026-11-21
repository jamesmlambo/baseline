<x-layouts.portal title="Reception" :booking="$booking">
    <h1 class="font-display text-2xl font-semibold text-lodge-900">Message reception</h1>
    <p class="mt-1 text-sm text-ink-900/60">For anything urgent or dangerous, phone reception or use the emergency numbers on the home page.</p>

    <ol class="mt-5 space-y-3" aria-label="Conversation">
        @forelse ($messages as $message)
            <li @class(['flex', 'justify-end' => $message->direction === 'in'])>
                <div @class([
                    'max-w-[85%] rounded-2xl px-4 py-2.5 text-sm shadow-sm',
                    'bg-lodge-800 text-white' => $message->direction === 'in',
                    'bg-white ring-1 ring-sand-200' => $message->direction === 'out',
                ])>
                    <p class="sr-only">{{ $message->direction === 'in' ? 'You' : 'Reception' }}:</p>
                    <p>{!! nl2br(e($message->body)) !!}</p>
                    <p @class(['mt-1 text-[0.7rem]', 'text-sand-200' => $message->direction === 'in', 'text-ink-900/50' => $message->direction === 'out'])>
                        {{ $message->created_at->setTimezone('Africa/Johannesburg')->format('D H:i') }}
                    </p>
                </div>
            </li>
        @empty
            <li class="text-sm text-ink-900/60">No messages yet.</li>
        @endforelse
    </ol>

    <form method="POST" action="{{ route('portal.messages.send') }}" class="mt-6 space-y-3" novalidate>
        @csrf
        <label for="body" class="sr-only">Your message</label>
        <textarea id="body" name="body" rows="3" required class="w-full rounded-xl border border-sand-300 px-3 py-2.5 focus:border-lodge-700 focus:outline-none focus:ring-2 focus:ring-lodge-700/30" placeholder="Type your message">{{ old('body') }}</textarea>
        @error('body')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        <button class="w-full rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Send</button>
    </form>
</x-layouts.portal>
