<?php

declare(strict_types=1);

namespace App\Filament\Tables;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Filament\Actions\PaymentActions;
use App\Models\Message;
use App\Services\Messaging\Messenger;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The message log table (MSG-05), shared by the Messages screen and each booking's Messages tab.
 */
final class MessageLog
{
    public static function configure(Table $table, bool $showBooking = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['template', 'booking']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No messages yet')
            ->columns(array_values(array_filter([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y H:i')->sortable(),
                $showBooking ? TextColumn::make('booking.reference')->label('Booking')->placeholder('—')->searchable() : null,
                TextColumn::make('template.name')->label('Message')->placeholder('Resent / manual')->wrap(),
                TextColumn::make('channel')->formatStateUsing(fn (MessageChannel $state): string => $state->label()),
                TextColumn::make('recipient')->label('To')->state(fn (Message $record): string => $record->maskedRecipient()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (MessageStatus $state): string => $state->label())
                    ->color(fn (MessageStatus $state): string => $state->color())
                    ->description(fn (Message $record): ?string => $record->skip_reason ?? $record->last_error),
                TextColumn::make('attempts')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('body')->label('Text')->limit(60)->tooltip(fn (Message $record): string => $record->body)->toggleable(),
            ])))
            ->filters([
                SelectFilter::make('status')->options(MessageStatus::options()),
                SelectFilter::make('channel')->options(MessageChannel::options()),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Message $record): bool => $record->body !== '' && in_array($record->status, [MessageStatus::Failed, MessageStatus::Sent, MessageStatus::Delivered], true))
                    ->authorize(fn (Message $record): bool => auth()->user()->can('resend', $record))
                    ->schema(fn (Message $record): array => [
                        Select::make('channel')
                            ->label('Send by')
                            ->options(MessageChannel::options())
                            ->default($record->channel->value)
                            ->required(),
                    ])
                    ->modalDescription('Sends the same text again as a new message. Use the other channel if this one failed.')
                    ->action(fn (Message $record, array $data) => PaymentActions::run(
                        fn () => app(Messenger::class)->resend($record, auth()->user(), MessageChannel::from($data['channel'])),
                        'Message queued',
                    )),
            ]);
    }
}
