<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Actions\Payments\VerifyEftPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\RoomUnavailableException;
use App\Filament\Forms\MoneyInput;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\PaymentLedger;
use App\Services\Payments\RefundWorkflow;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

/**
 * Payment buttons shared by the booking screen and the payments queue. Every button goes through
 * the same services the tests cover, so the rules (verification, dual approval) cannot be skipped.
 */
final class PaymentActions
{
    public static function record(): Action
    {
        return Action::make('recordPayment')
            ->label('Record payment')
            ->icon('heroicon-o-banknotes')
            ->authorize(fn (): bool => auth()->user()->can('create', Payment::class))
            ->schema([
                Select::make('method')
                    ->options([PaymentMethod::Cash->value => 'Cash', PaymentMethod::Eft->value => 'EFT (bank transfer)'])
                    ->required()
                    ->live(),
                MoneyInput::make('amount')->label('Amount')->required(),
                TextInput::make('bank_reference')->label('Bank or slip reference')->maxLength(120),
                Textarea::make('notes')->rows(2),
            ])
            ->modalDescription('Cash is recorded as received. An EFT stays "pending verification" until the owner or a manager checks the bank statement.')
            ->action(function (Booking $record, array $data): void {
                self::run(fn () => app(PaymentLedger::class)->recordAtReception(
                    $record,
                    PaymentMethod::from($data['method']),
                    $data['amount'] instanceof Money ? $data['amount'] : Money::fromRands((string) $data['amount']),
                    auth()->user(),
                    $data['bank_reference'] ?? null,
                    $data['notes'] ?? null,
                ), 'Payment recorded');
            });
    }

    public static function verify(): Action
    {
        return Action::make('verifyEft')
            ->label('Verify EFT')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Payment $record): bool => $record->method === PaymentMethod::Eft && $record->status === PaymentRecordStatus::PendingVerification)
            ->authorize(fn (Payment $record): bool => auth()->user()->can('verify', $record))
            ->schema([
                TextInput::make('bank_reference')->label('Bank statement reference you checked')->required()->maxLength(120),
                MoneyInput::make('amount_seen')->label('Amount on the statement')->required(),
            ])
            ->modalDescription('Only verify once the money shows on the bank statement. Proof of payment alone is not enough.')
            ->action(function (Payment $record, array $data): void {
                self::run(fn () => app(VerifyEftPayment::class)->handle(
                    $record,
                    auth()->user(),
                    $data['bank_reference'],
                    $data['amount_seen'] instanceof Money ? $data['amount_seen'] : Money::fromRands((string) $data['amount_seen']),
                ), 'EFT verified');
            });
    }

    public static function reject(): Action
    {
        return Action::make('rejectEft')
            ->label('Not received')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Payment $record): bool => $record->method === PaymentMethod::Eft && $record->status === PaymentRecordStatus::PendingVerification)
            ->authorize(fn (Payment $record): bool => auth()->user()->can('verify', $record))
            ->schema([Textarea::make('reason')->required()->rows(2)])
            ->action(fn (Payment $record, array $data) => self::run(
                fn () => app(VerifyEftPayment::class)->reject($record, auth()->user(), $data['reason']),
                'Marked as not received',
            ));
    }

    public static function requestRefund(): Action
    {
        return Action::make('requestRefund')
            ->label('Request refund')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (Payment $record): bool => $record->status === PaymentRecordStatus::Succeeded && $record->refundableAmount()->cents > 0)
            ->authorize(fn (Payment $record): bool => auth()->user()->can('requestRefund', $record))
            ->schema([
                MoneyInput::make('amount')->label('Amount to refund')->required(),
                Textarea::make('reason')->required()->rows(2),
            ])
            ->fillForm(fn (Payment $record): array => ['amount' => $record->refundableAmount()])
            ->modalDescription('A different authorised person must approve this before any money is returned.')
            ->action(fn (Payment $record, array $data) => self::run(
                fn () => app(RefundWorkflow::class)->request(
                    $record,
                    $data['amount'] instanceof Money ? $data['amount'] : Money::fromRands((string) $data['amount']),
                    $data['reason'],
                    auth()->user(),
                ),
                'Refund requested — waiting for a second approval',
            ));
    }

    public static function resolveException(): Action
    {
        return Action::make('resolveException')
            ->label('Mark as resolved')
            ->icon('heroicon-o-check')
            ->visible(fn (Payment $record): bool => $record->exception_reason !== null)
            ->authorize(fn (Payment $record): bool => auth()->user()->can('verify', $record))
            ->requiresConfirmation()
            ->modalDescription('Only resolve once the booking has been reinstated or the money refunded.')
            ->action(fn (Payment $record) => self::run(fn () => app(PaymentLedger::class)->resolveException($record), 'Resolved'));
    }

    /**
     * Run a domain call and turn rule violations into friendly notifications.
     */
    public static function run(callable $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->title($success)->success()->send();
        } catch (Throwable $exception) {
            if (! $exception instanceof \InvalidArgumentException
                && ! $exception instanceof AuthorizationException
                && ! $exception instanceof RoomUnavailableException
                && ! $exception instanceof InvalidBookingTransitionException) {
                throw $exception;
            }

            Notification::make()->title('Not done')->body($exception->getMessage())->danger()->send();
        }
    }
}
