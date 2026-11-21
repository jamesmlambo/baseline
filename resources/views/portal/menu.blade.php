<x-layouts.portal title="Menu" :booking="$booking">
    <h1 class="font-display text-2xl font-semibold text-lodge-900">Order to your room</h1>

    @if ($menu->isEmpty())
        <p class="mt-4 rounded-2xl bg-white p-5 text-ink-900/70 shadow-sm ring-1 ring-sand-200">The menu isn't available right now. <a class="font-medium text-lodge-700 underline" href="{{ route('portal.messages') }}">Message reception</a> instead.</p>
    @else
        <form method="POST" action="{{ route('portal.menu.order') }}" class="mt-4 space-y-6" novalidate>
            @csrf
            @error('quantities')<p class="rounded-xl bg-red-50 p-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ $message }}</p>@enderror

            @foreach ($menu as $category => $items)
                <section>
                    <h2 class="font-display text-lg font-semibold text-lodge-900">{{ $category }}</h2>
                    @if ($rule = $orderingHours->get($category))
                        <p class="text-sm {{ $rule->isOpenAt(now()->toImmutable()) ? 'text-ink-900/70' : 'font-semibold text-amber-800' }}">
                            Orders taken {{ $rule->windowLabel() }}{{ $rule->isOpenAt(now()->toImmutable()) ? '' : ' — closed right now' }}.@if ($rule->note) {{ $rule->note }}@endif
                        </p>
                    @endif
                    <ul class="mt-2 divide-y divide-sand-200 rounded-2xl bg-white shadow-sm ring-1 ring-sand-200">
                        @foreach ($items as $item)
                            <li class="flex items-start justify-between gap-4 p-4">
                                <div>
                                    <p class="font-semibold">{{ $item->name }} @if ($item->is_demo)<x-site.demo-badge />@endif</p>
                                    @if ($item->description)<p class="text-sm text-ink-900/70">{{ $item->description }}</p>@endif
                                    @if ($item->allergens)<p class="mt-1 text-xs text-amber-800">Contains: {{ $item->allergens }}</p>@endif
                                    <p class="mt-1 text-sm font-semibold">{{ $item->price_cents->format() }}</p>
                                </div>
                                <div class="shrink-0">
                                    <label for="qty-{{ $item->id }}" class="sr-only">Quantity of {{ $item->name }}</label>
                                    <input id="qty-{{ $item->id }}" type="number" name="quantities[{{ $item->id }}]" min="0" max="20" value="{{ old('quantities.'.$item->id, 0) }}"
                                        class="w-16 rounded-lg border border-sand-300 px-2 py-2 text-center">
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <x-site.field name="notes" label="Notes for the kitchen (optional)" type="textarea" hint="Allergies, how you like it, when to bring it." />
            <label class="flex items-start gap-3 text-sm">
                <input type="hidden" name="charge_to_room" value="0">
                <input type="checkbox" name="charge_to_room" value="1" checked class="mt-1 size-4 rounded border-sand-300 text-lodge-800">
                <span>Add it to my room bill</span>
            </label>
            <button class="w-full rounded-full bg-lodge-800 px-6 py-3 font-semibold text-white hover:bg-lodge-700">Send order</button>
        </form>
    @endif
</x-layouts.portal>
