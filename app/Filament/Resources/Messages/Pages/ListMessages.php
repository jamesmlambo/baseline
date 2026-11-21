<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Pages;

use App\Enums\MessageChannel;
use App\Filament\Resources\Messages\MessageResource;
use App\Services\Messaging\SpendGuard;
use Filament\Resources\Pages\ListRecords;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    /**
     * Shows this month's spend against each channel's cap (MSG-06), and that nothing is sent for real yet.
     */
    public function getSubheading(): ?string
    {
        $spend = app(SpendGuard::class);
        $parts = [];

        foreach (MessageChannel::cases() as $channel) {
            $cap = $spend->cap($channel);
            $parts[] = $channel->label().': '.$spend->spentThisMonth($channel)->format().($cap ? ' of '.$cap->format() : ' (no cap set)');
        }

        $drivers = collect(MessageChannel::cases())
            ->filter(fn (MessageChannel $channel): bool => config('messaging.'.$channel->value) === 'log')
            ->map(fn (MessageChannel $channel): string => $channel->label());

        $note = $drivers->isNotEmpty() ? ' · '.$drivers->implode(' and ').' only written to the log, not sent (no provider chosen yet).' : '';

        return 'This month: '.implode(' · ', $parts).$note;
    }
}
