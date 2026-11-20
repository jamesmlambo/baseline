<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The payments ledger, with the EFT verification queue and payment exceptions (PAY-07, PAY-10).
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        $count = Payment::query()
            ->where(fn (Builder $query) => $query->where('status', PaymentRecordStatus::PendingVerification)->orWhereNotNull('exception_reason'))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'EFTs to verify and payments needing attention';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['booking', 'receipt']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y H:i')->sortable(),
                TextColumn::make('booking.reference')
                    ->label('Booking')
                    ->searchable()
                    ->url(fn (Payment $record): ?string => $record->booking ? BookingResource::getUrl('view', ['record' => $record->booking]) : null),
                TextColumn::make('booking.lead_guest_name')->label('Guest'),
                TextColumn::make('method')->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                TextColumn::make('amount_cents')->label('Amount')->formatStateUsing(fn (Money $state): string => $state->format())->alignEnd(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PaymentRecordStatus $state): string => $state->label())
                    ->color(fn (PaymentRecordStatus $state): string => $state->color()),
                TextColumn::make('exception_reason')->label('Attention')->color('danger')->wrap()->placeholder('—'),
                TextColumn::make('bank_reference')->label('Reference')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Filter::make('needs_action')
                    ->label('Needs action')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
                        ->where('status', PaymentRecordStatus::PendingVerification)
                        ->orWhereNotNull('exception_reason'))),
                SelectFilter::make('status')->options(PaymentRecordStatus::options()),
                SelectFilter::make('method')->options(PaymentMethod::options()),
            ])
            ->recordActions([
                PaymentActions::verify(),
                PaymentActions::reject(),
                PaymentActions::resolveException(),
                PaymentActions::requestRefund(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
