<x-filament-panels::page>
    <div class="flex items-center gap-2">
        <x-filament::button color="gray" size="sm" icon="heroicon-o-chevron-left" wire:click="previousWeek">Previous week</x-filament::button>
        <span class="text-sm font-medium">Week of {{ $this->monday()->format('j M Y') }}</span>
        <x-filament::button color="gray" size="sm" icon="heroicon-o-chevron-right" icon-position="after" wire:click="nextWeek">Next week</x-filament::button>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full text-sm">
            <caption class="sr-only">Roster for the week</caption>
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th scope="col" class="p-3 text-left">Employee</th>
                    @foreach ($days as $day)
                        <th scope="col" class="p-3 text-left whitespace-nowrap {{ $day->isToday() ? 'text-primary-600' : '' }}">{{ $day->format('D j M') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($rows as $row)
                    <tr>
                        <th scope="row" class="p-3 text-left font-medium">
                            {{ $row['employee']->displayName() }}
                            <span class="block text-xs font-normal text-gray-500">{{ $row['employee']->department?->name }}</span>
                        </th>
                        @foreach ($row['days'] as $rosterDay)
                            <td class="p-3 align-top">
                                @if ($rosterDay->isWorking())
                                    <span class="font-medium">{{ $rosterDay->shift->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $rosterDay->shift->timesLabel() }}</span>
                                    @if ($rosterDay->coveringFor)
                                        <span class="block text-xs text-warning-600">Cover for {{ $rosterDay->coveringFor->displayName() }}</span>
                                    @endif
                                @elseif ($rosterDay->kind === 'leave')
                                    <span class="text-info-600">{{ $rosterDay->label() }}</span>
                                @else
                                    <span class="text-gray-400">{{ $rosterDay->label() }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-6 text-center text-gray-500">No active employees yet. Add them under People › Employees.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
