@php
    $styles = [
        'free' => 'background:transparent;',
        'hold' => 'background:#fde68a;color:#78350f;',
        'confirmed' => 'background:#99f6e4;color:#134e4a;',
        'in_house' => 'background:#0f766e;color:#fff;',
        'departed' => 'background:#e7e5e4;color:#57534e;',
        'block' => 'background:repeating-linear-gradient(45deg,#d6d3d1,#d6d3d1 6px,#e7e5e4 6px,#e7e5e4 12px);color:#44403c;',
    ];
    $legend = ['hold' => 'Hold (awaiting payment)', 'confirmed' => 'Confirmed', 'in_house' => 'In house', 'departed' => 'Checked out', 'block' => 'Blocked'];
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between;">
        <div style="display:flex;gap:.5rem;">
            <x-filament::button wire:click="move(-7)" color="gray" icon="heroicon-o-chevron-double-left" size="sm">Week</x-filament::button>
            <x-filament::button wire:click="move(-1)" color="gray" icon="heroicon-o-chevron-left" size="sm">Day</x-filament::button>
            <x-filament::button wire:click="today" color="gray" size="sm">Today</x-filament::button>
            <x-filament::button wire:click="move(1)" color="gray" icon="heroicon-o-chevron-right" icon-position="after" size="sm">Day</x-filament::button>
            <x-filament::button wire:click="move(7)" color="gray" icon="heroicon-o-chevron-double-right" icon-position="after" size="sm">Week</x-filament::button>
        </div>
        <ul style="display:flex;flex-wrap:wrap;gap:.75rem;font-size:.75rem;list-style:none;margin:0;padding:0;" aria-label="Legend">
            @foreach ($legend as $kind => $label)
                <li style="display:flex;align-items:center;gap:.35rem;"><span style="display:inline-block;width:.9rem;height:.9rem;border-radius:.2rem;{{ $styles[$kind] }}"></span>{{ $label }}</li>
            @endforeach
        </ul>
    </div>

    <div style="overflow-x:auto;border-radius:.75rem;border:1px solid rgba(120,113,108,.25);" class="bg-white dark:bg-gray-900">
        <table style="border-collapse:collapse;width:100%;min-width:{{ 160 + count($dates) * 72 }}px;font-size:.75rem;">
            <caption class="sr-only">Rooms by night</caption>
            <thead>
                <tr>
                    <th scope="col" style="position:sticky;left:0;z-index:1;text-align:left;padding:.5rem .75rem;border-bottom:1px solid rgba(120,113,108,.25);" class="bg-white dark:bg-gray-900">Room</th>
                    @foreach ($dates as $date)
                        <th scope="col" style="padding:.4rem .25rem;text-align:center;border-bottom:1px solid rgba(120,113,108,.25);border-left:1px solid rgba(120,113,108,.12);{{ $date->isToday() ? 'background:rgba(13,148,136,.12);' : '' }}{{ $date->isWeekend() ? 'font-style:italic;' : '' }}">
                            <div style="font-weight:600;">{{ $date->format('D') }}</div>
                            <div>{{ $date->format('j M') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rooms as $row)
                    <tr>
                        <th scope="row" style="position:sticky;left:0;z-index:1;text-align:left;padding:.5rem .75rem;border-bottom:1px solid rgba(120,113,108,.12);white-space:nowrap;" class="bg-white dark:bg-gray-900">
                            <div style="font-weight:600;">{{ $row['room']->name }}</div>
                            <div style="opacity:.6;">{{ $row['room']->accommodationType->name }}@unless ($row['room']->is_active) · inactive @endunless</div>
                        </th>
                        @foreach ($row['cells'] as $index => $cell)
                            <td style="padding:2px;border-bottom:1px solid rgba(120,113,108,.12);border-left:1px solid rgba(120,113,108,.12);height:2.5rem;{{ $dates[$index]->isToday() ? 'background:rgba(13,148,136,.06);' : '' }}">
                                @if ($cell['kind'] !== 'free')
                                    @php($title = $cell['kind'] === 'block' ? $cell['label'] : $cell['booking']->reference.' · '.$cell['label'].' · '.$cell['booking']->status->label())
                                    @if (isset($cell['booking']))
                                        <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $cell['booking']]) }}" title="{{ $title }}"
                                           style="display:block;height:100%;border-radius:.3rem;padding:.25rem .35rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;text-decoration:none;{{ $styles[$cell['kind']] }}">
                                            {{ $cell['starts'] ? $cell['label'] : '' }}<span class="sr-only">{{ $cell['starts'] ? '' : $title }}</span>
                                        </a>
                                    @else
                                        <div title="{{ $title }}" style="height:100%;border-radius:.3rem;padding:.25rem .35rem;overflow:hidden;white-space:nowrap;{{ $styles['block'] }}">
                                            {{ $cell['starts'] ? $cell['label'] : '' }}<span class="sr-only">{{ $cell['starts'] ? '' : $title }}</span>
                                        </div>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($dates) + 1 }}" style="padding:1.5rem;text-align:center;opacity:.7;">No rooms yet. Add them under Bookings › Rooms &amp; rates.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
