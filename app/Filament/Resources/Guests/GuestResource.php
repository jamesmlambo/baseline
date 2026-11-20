<?php

declare(strict_types=1);

namespace App\Filament\Resources\Guests;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Guests\Pages\EditGuest;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Models\Guest;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Guest profiles (BOOK-06). Email and phone are encrypted, so search is by name,
 * or by exact email/phone through the lookup hashes.
 */
class GuestResource extends Resource
{
    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Guest')
                ->columns(2)
                ->schema([
                    TextInput::make('first_name')->required()->maxLength(80),
                    TextInput::make('last_name')->label('Surname')->required()->maxLength(80),
                    TextInput::make('email')->email()->maxLength(190),
                    TextInput::make('phone')->tel()->maxLength(32),
                    Textarea::make('notes')->label('Notes and preferences')->rows(3)->columnSpanFull()
                        ->helperText('Only record what is needed for the stay. Guests can ask to see this (POPIA).'),
                    Toggle::make('marketing_consent')->label('Agreed to receive news and specials'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')->label('Surname')->sortable(),
                TextColumn::make('first_name')->label('First name'),
                TextColumn::make('email')->placeholder('—'),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('bookings_count')->label('Bookings')->counts('bookings')->sortable(),
                IconColumn::make('marketing_consent')->label('Marketing')->boolean(),
            ])
            ->searchable()
            ->searchUsing(fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email_hash', Guest::lookupHash('email', $search))
                    ->orWhere('phone_hash', Guest::lookupHash('phone', $search));
            }))
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
            'edit' => EditGuest::route('/{record}/edit'),
        ];
    }

    public static function bookingsUrl(): string
    {
        return BookingResource::getUrl();
    }
}
