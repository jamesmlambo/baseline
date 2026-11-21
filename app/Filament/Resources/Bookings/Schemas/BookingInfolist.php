<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Booking')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference')->weight('bold')->copyable(),
                        TextEntry::make('status')->badge()->formatStateUsing(fn (BookingStatus $state): string => $state->label()),
                        TextEntry::make('payment_status')->label('Payment')->badge()->formatStateUsing(fn (PaymentStatus $state): string => $state->label()),
                        TextEntry::make('arrival_date')->label('Arrive')->date('l j M Y'),
                        TextEntry::make('departure_date')->label('Depart')->date('l j M Y'),
                        TextEntry::make('nights')->state(fn (Booking $record): int => $record->nights()),
                        TextEntry::make('hold_expires_at')->label('Hold until')->dateTime('j M Y H:i')->placeholder('—')
                            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Hold),
                        TextEntry::make('source')->formatStateUsing(fn (BookingSource $state): string => $state->label()),
                        TextEntry::make('external_reference')->placeholder('—'),
                        TextEntry::make('special_requests')->columnSpanFull()->placeholder('None'),
                    ]),
                Section::make('Guest')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('lead_guest_name')->label('Name'),
                        TextEntry::make('lead_guest_email')->label('Email')->copyable()->placeholder('—'),
                        TextEntry::make('lead_guest_phone')->label('Phone')->copyable()->placeholder('—'),
                        TextEntry::make('guest.bookings_count')
                            ->label('Stays with us')
                            ->state(fn (Booking $record): int => $record->guest?->bookings()->count() ?? 1),
                        TextEntry::make('checked_in_at')->label('Checked in')->dateTime('j M Y H:i')->placeholder('—'),
                        TextEntry::make('checked_out_at')->label('Checked out')->dateTime('j M Y H:i')->placeholder('—'),
                        TextEntry::make('register')
                            ->label('Guest register')
                            ->state(fn (Booking $record): ?string => ($entry = $record->registerEntry)
                                ? collect([$entry->full_name, $entry->maskedIdNumber(), $entry->nationality, $entry->vehicle_registration])->filter()->implode(' · ')
                                : null)
                            ->placeholder('Not captured yet'),
                    ]),
                Section::make('Rooms and extras')
                    ->columnSpan(2)
                    ->schema([
                        RepeatableEntry::make('activeBookingRooms')
                            ->label('Assigned rooms')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('room.name')->label('Room'),
                                TextEntry::make('accommodationType.name')->label('Type'),
                                TextEntry::make('guests')->state(fn ($record): string => $record->adults.' adults'.($record->children ? ', '.$record->children.' children' : '')),
                                TextEntry::make('accommodation_total_cents')->label('Price')->formatStateUsing(fn (Money $state): string => $state->format()),
                            ]),
                        RepeatableEntry::make('extras')
                            ->columns(3)
                            ->visible(fn (Booking $record): bool => $record->extras()->exists())
                            ->schema([
                                TextEntry::make('description'),
                                TextEntry::make('quantity'),
                                TextEntry::make('total_cents')->label('Total')->formatStateUsing(fn (Money $state): string => $state->format()),
                            ]),
                    ]),
                Section::make('Money')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('owed')->label('Total invoiced')->state(fn (Booking $record): string => $record->owedTotal()->format())->weight('bold'),
                        TextEntry::make('deposit_due_cents')->label('Needed to confirm')->formatStateUsing(fn (Money $state): string => $state->format()),
                        TextEntry::make('paid')->label('Paid (verified)')->state(fn (Booking $record): string => $record->paidTotal()->format()),
                        TextEntry::make('balance')
                            ->label('Balance due')
                            ->state(fn (Booking $record): string => $record->balanceDue()->format())
                            ->color(fn (Booking $record): string => $record->balanceDue()->cents > 0 ? 'danger' : 'success'),
                        RepeatableEntry::make('invoices')
                            ->label('Invoices')
                            ->schema([
                                TextEntry::make('number')
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn ($state, $record): string => $state.' · '.$record->total_cents->format())
                                    ->url(fn ($record): string => route('documents.show', ['invoice', $record->public_token]), shouldOpenInNewTab: true),
                            ]),
                    ]),
                Section::make('History')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('statusHistory')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('created_at')->label('When')->dateTime('j M Y H:i'),
                                TextEntry::make('to_status')->label('Status')->formatStateUsing(fn (BookingStatus $state): string => $state->label()),
                                TextEntry::make('changedBy.name')->label('By')->placeholder('System / guest'),
                                TextEntry::make('reason')->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
