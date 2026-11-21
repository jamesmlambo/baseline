<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestMessages;

use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\GuestMessages\Pages\ListGuestMessages;
use App\Models\GuestMessage;
use App\Services\Portal\GuestRequests;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Reception inbox for messages guests send from the portal (GST-07/08).
 */
class GuestMessageResource extends Resource
{
    protected static ?string $model = GuestMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?string $navigationLabel = 'Guest messages';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $unread = GuestMessage::query()->where('direction', GuestMessage::IN)->whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('15s')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('direction', GuestMessage::IN)->with('booking.activeBookingRooms.room'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->sortable(),
                TextColumn::make('room')->label('Room')->weight('bold')
                    ->state(fn (GuestMessage $record): string => $record->booking->activeBookingRooms->first()?->room?->name ?? '—'),
                TextColumn::make('booking.lead_guest_name')->label('Guest')
                    ->url(fn (GuestMessage $record): string => BookingResource::getUrl('view', ['record' => $record->booking])),
                TextColumn::make('body')->label('Message')->wrap()->limit(160),
                TextColumn::make('read_at')->label('Answered')->since()->placeholder('Not yet')
                    ->color(fn (GuestMessage $record): ?string => $record->read_at === null ? 'danger' : null),
            ])
            ->filters([
                TernaryFilter::make('unanswered')
                    ->label('Unanswered only')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('read_at'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('read_at'),
                    ),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->authorize(fn (GuestMessage $record): bool => auth()->user()->can('update', $record->booking))
                    ->modalHeading(fn (GuestMessage $record): string => 'Conversation with '.$record->booking->lead_guest_name)
                    ->schema(fn (GuestMessage $record): array => [
                        Text::make(new HtmlString(
                            $record->booking->guestMessages()->latest('id')->take(8)->get()->reverse()
                                ->map(fn (GuestMessage $message): string => '<p style="margin:.25rem 0;"><strong>'.($message->direction === GuestMessage::IN ? 'Guest' : 'Reception').'</strong> · '
                                    .e($message->created_at->format('H:i')).'<br>'.nl2br(e($message->body)).'</p>')
                                ->implode(''),
                        )),
                        Textarea::make('body')->label('Your reply')->required()->rows(3)->maxLength(2000),
                    ])
                    ->action(fn (GuestMessage $record, array $data) => PaymentActions::run(
                        fn () => app(GuestRequests::class)->reply($record->booking, $data['body'], auth()->user()),
                        'Reply sent to the guest portal',
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuestMessages::route('/'),
        ];
    }
}
