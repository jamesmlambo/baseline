<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoomBlocks\Schemas;

use App\Enums\RoomBlockReason;
use App\Models\Room;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoomBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Take a room out of service')
                    ->description('The room cannot be booked on these nights. If it already has bookings, move them first.')
                    ->columns(2)
                    ->schema([
                        Select::make('room_id')
                            ->label('Room')
                            ->options(fn (): array => Room::query()
                                ->with('accommodationType')
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn (Room $room): array => [$room->id => "{$room->name} ({$room->accommodationType->name})"])
                                ->all())
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        DatePicker::make('starts_on')
                            ->label('First night out of service')
                            ->required()
                            ->native(false),
                        DatePicker::make('ends_on')
                            ->label('Back in service on')
                            ->helperText('The first night the room can be booked again.')
                            ->required()
                            ->native(false)
                            ->after('starts_on'),
                        Select::make('reason')
                            ->options(RoomBlockReason::options())
                            ->required(),
                        Textarea::make('notes')
                            ->rows(2),
                    ]),
            ]);
    }
}
