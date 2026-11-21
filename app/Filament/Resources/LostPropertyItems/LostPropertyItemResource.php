<?php

declare(strict_types=1);

namespace App\Filament\Resources\LostPropertyItems;

use App\Enums\LostPropertyStatus;
use App\Filament\Resources\LostPropertyItems\Pages\ManageLostPropertyItems;
use App\Models\Booking;
use App\Models\LostPropertyItem;
use App\Models\Room;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Lost-property log (HK-10). Front desk, managers and the owner only.
 */
class LostPropertyItemResource extends Resource
{
    protected static ?string $model = LostPropertyItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?string $navigationLabel = 'Lost property';

    protected static ?string $modelLabel = 'lost-property item';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('found_on')->required()->default(today())->maxDate(today()),
            TextInput::make('found_at')->label('Where it was found')->required()->maxLength(255),
            Select::make('room_id')->label('Room')->options(fn (): array => Room::query()->orderBy('sort_order')->pluck('name', 'id')->all())->searchable(),
            Select::make('booking_id')->label('Booking (if known)')
                ->getSearchResultsUsing(fn (string $search): array => Booking::query()->where('reference', 'ilike', "%{$search}%")->limit(10)->pluck('reference', 'id')->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => Booking::query()->whereKey($value)->value('reference'))
                ->searchable(),
            Textarea::make('description')->required()->rows(2)->columnSpanFull(),
            TextInput::make('storage_location')->label('Kept where')->maxLength(255),
            Select::make('status')->options(LostPropertyStatus::options())->default(LostPropertyStatus::Held->value)->required(),
            Textarea::make('outcome_notes')->label('Outcome (who collected it, courier, etc.)')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['room', 'booking']))
            ->defaultSort('found_on', 'desc')
            ->columns([
                TextColumn::make('found_on')->date('j M Y')->sortable(),
                TextColumn::make('description')->wrap()->limit(80)->searchable(),
                TextColumn::make('found_at')->label('Found at')->description(fn (LostPropertyItem $record): ?string => $record->room?->name),
                TextColumn::make('booking.reference')->label('Booking')->placeholder('—'),
                TextColumn::make('storage_location')->label('Kept')->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (LostPropertyStatus $state): string => $state->label())
                    ->color(fn (LostPropertyStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->options(LostPropertyStatus::options())->default(LostPropertyStatus::Held->value),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLostPropertyItems::route('/'),
        ];
    }
}
