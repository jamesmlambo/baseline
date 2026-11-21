<x-filament-panels::page>
    <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10">
        @foreach ($days as $day)
            <li class="flex items-center justify-between gap-4 p-4 {{ $day->date->isToday() ? 'bg-primary-50 dark:bg-primary-950/30' : '' }}">
                <span class="font-medium">{{ $day->date->isToday() ? 'Today' : $day->date->format('D j M') }}</span>
                <span class="text-right {{ $day->isWorking() ? 'font-semibold' : 'text-gray-500' }}">{{ $day->label() }}</span>
            </li>
        @endforeach
    </ul>
</x-filament-panels::page>
