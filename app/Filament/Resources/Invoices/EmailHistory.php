<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices;

use App\Models\DocumentDelivery;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * "Has this been sent, where and how often?" for invoices and quotations (PAY-02).
 */
final class EmailHistory
{
    /**
     * List column. The table must eager-load `deliveries`.
     */
    public static function column(): TextColumn
    {
        return TextColumn::make('email_status')
            ->label('Emailed')
            ->state(fn (Model $record): string => $record->emailStatus())
            ->badge()
            ->color(fn (string $state): string => $state === 'Not sent' ? 'gray' : 'success')
            ->tooltip(fn (Model $record): ?string => $record->emailSummary()
                ->map(fn (array $row): string => "{$row['email']}: {$row['times']}×, last ".$row['last_sent_at']->setTimezone('Africa/Johannesburg')->format('j M H:i'))
                ->implode("\n") ?: null);
    }

    /**
     * Document page section: each address with how many times and when last, then every send.
     */
    public static function section(): Section
    {
        return Section::make('Email history')
            ->description(fn (Model $record): string => $record->emailStatus())
            ->collapsible()
            ->schema([
                RepeatableEntry::make('email_summary')
                    ->label('Sent to')
                    ->state(fn (Model $record): array => $record->emailSummary()->all())
                    ->columns(3)
                    ->placeholder('Not emailed yet.')
                    ->schema([
                        TextEntry::make('email')->label('Email address'),
                        TextEntry::make('times')->label('Times sent')->formatStateUsing(fn (int $state): string => "{$state}×"),
                        TextEntry::make('last_sent_at')->label('Last sent')->dateTime('j M Y H:i', 'Africa/Johannesburg'),
                    ]),
                RepeatableEntry::make('deliveries')
                    ->label('Every email')
                    ->columns(4)
                    ->visible(fn (Model $record): bool => $record->deliveries()->exists())
                    ->schema([
                        TextEntry::make('sent_at')->label('When')->dateTime('j M Y H:i', 'Africa/Johannesburg'),
                        TextEntry::make('recipient')->label('To'),
                        TextEntry::make('reason')->label('How')->formatStateUsing(fn (string $state, DocumentDelivery $record): string => $record->reasonLabel()),
                        TextEntry::make('sender.name')->label('By')->placeholder('System'),
                    ]),
            ]);
    }
}
