<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Filament\Actions\PaymentActions;
use App\Models\Payment;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y H:i'),
                TextColumn::make('method')->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                TextColumn::make('amount_cents')->label('Amount')->formatStateUsing(fn (Money $state): string => $state->format()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PaymentRecordStatus $state): string => $state->label())
                    ->color(fn (PaymentRecordStatus $state): string => $state->color()),
                TextColumn::make('refunded_cents')->label('Refunded')
                    ->formatStateUsing(fn (Money $state): string => $state->isZero() ? '—' : $state->format()),
                TextColumn::make('bank_reference')->label('Reference')->placeholder('—'),
                TextColumn::make('exception_reason')->label('Attention')->color('danger')->wrap()->placeholder('—'),
                TextColumn::make('receipt.number')->label('Receipt')
                    ->url(fn (Payment $record): ?string => $record->receipt ? route('documents.show', ['receipt', $record->receipt->public_token]) : null, shouldOpenInNewTab: true)
                    ->placeholder('—'),
            ])
            ->recordActions([
                PaymentActions::verify(),
                PaymentActions::reject(),
                PaymentActions::requestRefund(),
                PaymentActions::resolveException(),
            ]);
    }
}
