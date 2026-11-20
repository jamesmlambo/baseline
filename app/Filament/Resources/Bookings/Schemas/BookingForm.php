<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingSource;
use App\Models\AccommodationType;
use App\Models\Extra;
use App\Services\Availability\AvailabilityService;
use App\Support\StayDates;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Throwable;

/**
 * Front-desk booking (REC-03, BOOK-01): same engine and conflict checks as the website.
 * Every booking starts as a hold with an expiry chosen by staff (no indefinite holds, PEND-01).
 */
class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Stay')
                    ->columns(2)
                    ->schema([
                        Select::make('accommodation_type_id')
                            ->label('Room type')
                            ->options(fn (): array => AccommodationType::query()->active()->orderBy('sort_order')->pluck('name', 'id')->all())
                            ->required()
                            ->live(),
                        Select::make('room_id')
                            ->label('Specific room (optional)')
                            ->helperText('Leave empty to assign the first free room automatically.')
                            ->options(fn (Get $get): array => self::freeRooms($get))
                            ->placeholder('Assign automatically'),
                        DatePicker::make('arrival')
                            ->required()
                            ->native(false)
                            ->minDate(today())
                            ->live(),
                        DatePicker::make('departure')
                            ->required()
                            ->native(false)
                            ->after('arrival')
                            ->live(),
                        TextInput::make('adults')->integer()->minValue(1)->maxValue(12)->default(2)->required(),
                        TextInput::make('children')->integer()->minValue(0)->maxValue(12)->default(0),
                        CheckboxList::make('extras')
                            ->options(fn (): array => Extra::query()->where('is_active', true)->orderBy('sort_order')->get()
                                ->mapWithKeys(fn (Extra $extra): array => [$extra->id => "{$extra->name} ({$extra->price_cents->format()} {$extra->pricing_unit->label()})"])
                                ->all())
                            ->columnSpanFull(),
                    ]),
                Section::make('Guest')
                    ->columns(2)
                    ->description('Returning guests are matched by email or phone and their profile is reused.')
                    ->schema([
                        TextInput::make('first_name')->required()->maxLength(80),
                        TextInput::make('last_name')->label('Surname')->required()->maxLength(80),
                        TextInput::make('email')->email()->maxLength(190)->requiredWithout('phone'),
                        TextInput::make('phone')->tel()->maxLength(32)->requiredWithout('email'),
                        Textarea::make('special_requests')->rows(2)->columnSpanFull(),
                        Toggle::make('marketing_consent')->label('Guest agreed to receive news and specials'),
                    ]),
                Section::make('Booking')
                    ->columns(2)
                    ->schema([
                        Select::make('source')
                            ->options(collect([BookingSource::WalkIn, BookingSource::Phone, BookingSource::Email, BookingSource::WhatsApp, BookingSource::Other])
                                ->mapWithKeys(fn (BookingSource $source): array => [$source->value => $source->label()])->all())
                            ->default(BookingSource::Phone->value)
                            ->required(),
                        DateTimePicker::make('hold_expires_at')
                            ->label('Hold the room until')
                            ->helperText('The room is released automatically if payment has not been received by then. Hold policy is pending a client decision.')
                            ->native(false)
                            ->seconds(false)
                            ->minDate(now())
                            ->default(now()->addDay()->setTime(12, 0))
                            ->required(),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function freeRooms(Get $get): array
    {
        $type = AccommodationType::query()->find($get('accommodation_type_id'));

        if ($type === null || blank($get('arrival')) || blank($get('departure'))) {
            return [];
        }

        try {
            $stay = new StayDates($get('arrival'), $get('departure'));
        } catch (Throwable) {
            return [];
        }

        return app(AvailabilityService::class)->availableRooms($type, $stay)->pluck('name', 'id')->all();
    }
}
