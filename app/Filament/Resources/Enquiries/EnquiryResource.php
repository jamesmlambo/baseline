<?php

declare(strict_types=1);

namespace App\Filament\Resources\Enquiries;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\Pages\ViewEnquiry;
use App\Models\Enquiry;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Website contact messages and booking requests (WEB-10). Read and update status only.
 */
class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?string $navigationLabel = 'Website enquiries';

    protected static ?string $modelLabel = 'enquiry';

    protected static ?string $pluralModelLabel = 'enquiries';

    public static function getNavigationBadge(): ?string
    {
        $new = Enquiry::query()->where('status', EnquiryStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('From')
                ->columns(3)
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('email')->copyable()->placeholder('—'),
                    TextEntry::make('phone')->copyable()->placeholder('—'),
                    TextEntry::make('type')->formatStateUsing(fn (EnquiryType $state): string => $state->label())->badge(),
                    TextEntry::make('status')->formatStateUsing(fn (EnquiryStatus $state): string => $state->label())->badge()->color(fn (EnquiryStatus $state): string => $state->color()),
                    TextEntry::make('created_at')->label('Received')->dateTime('j M Y H:i'),
                    IconEntry::make('marketing_consent')->label('Agreed to marketing')->boolean(),
                ]),
            Section::make('Requested stay')
                ->columns(3)
                ->visible(fn (Enquiry $record): bool => $record->type === EnquiryType::BookingRequest)
                ->schema([
                    TextEntry::make('accommodationType.name')->label('Room type'),
                    TextEntry::make('arrival_date')->label('Arrival')->date('D j M Y'),
                    TextEntry::make('departure_date')->label('Departure')->date('D j M Y'),
                    TextEntry::make('adults'),
                    TextEntry::make('children'),
                    TextEntry::make('quoted_total_cents')->label('Price shown to guest')->formatStateUsing(fn (?Money $state): string => $state?->format() ?? '—'),
                ]),
            Section::make('Message')
                ->schema([TextEntry::make('message')->hiddenLabel()->placeholder('No message')->columnSpanFull()]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('accommodationType'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Received')->dateTime('j M H:i')->sortable(),
                TextColumn::make('type')->formatStateUsing(fn (EnquiryType $state): string => $state->label())->badge()->color('gray'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('accommodationType.name')->label('Room type')->placeholder('—'),
                TextColumn::make('arrival_date')->label('Arrival')->date('j M Y')->placeholder('—'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (EnquiryStatus $state): string => $state->label())->color(fn (EnquiryStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->options(EnquiryStatus::options())->default(EnquiryStatus::New->value),
                SelectFilter::make('type')->options([EnquiryType::Contact->value => 'Contact form', EnquiryType::BookingRequest->value => 'Booking request']),
            ])
            ->recordActions([
                ViewAction::make(),
                self::changeStatusAction(),
            ]);
    }

    public static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Update status')
            ->icon('heroicon-o-check-circle')
            ->authorize(fn (Enquiry $record): bool => auth()->user()->can('update', $record))
            ->schema([
                Select::make('status')->options(EnquiryStatus::options())->required(),
            ])
            ->fillForm(fn (Enquiry $record): array => ['status' => $record->status->value])
            ->action(function (Enquiry $record, array $data): void {
                $record->update([
                    'status' => $data['status'],
                    'handled_by' => auth()->id(),
                    'handled_at' => now(),
                ]);

                Notification::make()->title('Enquiry updated')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnquiries::route('/'),
            'view' => ViewEnquiry::route('/{record}'),
        ];
    }
}
