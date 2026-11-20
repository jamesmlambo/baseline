<?php

declare(strict_types=1);

namespace App\Filament\Resources\Refunds;

use App\Enums\RefundStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Models\Refund;
use App\Services\Payments\RefundWorkflow;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Refund approvals (DEC-06). The approve button is hidden from the person who asked for the refund.
 */
class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?int $navigationSort = 30;

    public static function getNavigationBadge(): ?string
    {
        $count = Refund::query()->where('status', RefundStatus::Requested)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['payment.booking', 'requester', 'approver']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Requested')->dateTime('j M Y H:i'),
                TextColumn::make('payment.booking.reference')
                    ->label('Booking')
                    ->url(fn (Refund $record): ?string => $record->payment->booking ? BookingResource::getUrl('view', ['record' => $record->payment->booking]) : null),
                TextColumn::make('amount_cents')->label('Amount')->formatStateUsing(fn (Money $state): string => $state->format()),
                TextColumn::make('reason')->wrap(),
                TextColumn::make('requester.name')->label('Asked by'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (RefundStatus $state): string => $state->label())
                    ->color(fn (RefundStatus $state): string => $state->color()),
                TextColumn::make('approver.name')->label('Approved by')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(RefundStatus::cases())->mapWithKeys(fn (RefundStatus $status): array => [$status->value => $status->label()])->all())
                    ->default(RefundStatus::Requested->value),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve and refund')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->authorize(fn (Refund $record): bool => auth()->user()->can('decide', $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Refund $record): string => "Refund {$record->amount_cents->format()} to the guest. A credit note is issued automatically.")
                    ->action(fn (Refund $record) => PaymentActions::run(fn () => app(RefundWorkflow::class)->approve($record, auth()->user()), 'Refund processed')),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->authorize(fn (Refund $record): bool => auth()->user()->can('decide', $record))
                    ->schema([Textarea::make('reason')->required()->rows(2)])
                    ->action(fn (Refund $record, array $data) => PaymentActions::run(fn () => app(RefundWorkflow::class)->reject($record, auth()->user(), $data['reason']), 'Refund rejected')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRefunds::route('/'),
        ];
    }
}
