<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\Kitchen;

use App\Enums\KitchenTicketStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Staff\Resources\Kitchen\Pages\ListKitchenTickets;
use App\Models\KitchenTicket;
use App\Services\Leisure\KitchenTickets;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The kitchen queue (LSR-04). Refreshes every 15 seconds; allergens are shown in red.
 */
class KitchenTicketResource extends Resource
{
    protected static ?string $model = KitchenTicket::class;

    protected static ?string $slug = 'kitchen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static ?string $navigationLabel = 'Kitchen';

    protected static ?string $modelLabel = 'kitchen ticket';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $new = KitchenTicket::query()->where('status', KitchenTicketStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('15s')
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('created_at')->label('Ordered')->dateTime('H:i', 'Africa/Johannesburg')
                    ->description(fn (KitchenTicket $record): ?string => $record->wanted_at ? 'for '.$record->wanted_at->setTimezone('Africa/Johannesburg')->format('D H:i') : null),
                TextColumn::make('for_label')->label('For')->weight('bold'),
                TextColumn::make('items')->label('Order')->listWithLineBreaks()
                    ->state(fn (KitchenTicket $record): array => array_map(fn (array $item): string => "{$item['quantity']} × {$item['name']}", $record->items ?? [])),
                TextColumn::make('allergens')->color('danger')->weight('bold')->wrap()->placeholder('—'),
                TextColumn::make('notes')->wrap()->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (KitchenTicketStatus $state): string => $state->label())
                    ->color(fn (KitchenTicketStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->multiple()->options(KitchenTicketStatus::options())
                    ->default([KitchenTicketStatus::New->value, KitchenTicketStatus::Preparing->value, KitchenTicketStatus::Ready->value]),
            ])
            ->recordActions([
                self::move('preparing', 'Start', KitchenTicketStatus::Preparing, 'heroicon-o-fire', 'warning', [KitchenTicketStatus::New]),
                self::move('ready', 'Ready', KitchenTicketStatus::Ready, 'heroicon-o-bell', 'success', [KitchenTicketStatus::New, KitchenTicketStatus::Preparing]),
                self::move('delivered', 'Served', KitchenTicketStatus::Delivered, 'heroicon-o-check', 'gray', [KitchenTicketStatus::Ready])
                    ->visible(fn (KitchenTicket $record): bool => $record->status === KitchenTicketStatus::Ready && $record->guest_request_id === null),
            ]);
    }

    /**
     * @param  list<KitchenTicketStatus>  $from
     */
    private static function move(string $name, string $label, KitchenTicketStatus $to, string $icon, string $color, array $from): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (KitchenTicket $record): bool => in_array($record->status, $from, true))
            ->authorize(fn (KitchenTicket $record): bool => auth()->user()->can('update', $record))
            ->action(fn (KitchenTicket $record) => PaymentActions::run(fn () => app(KitchenTickets::class)->advance($record, $to, auth()->user()), $to->label()));
    }

    public static function getPages(): array
    {
        return ['index' => ListKitchenTickets::route('/')];
    }
}
