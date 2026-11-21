<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\RelationManagers;

use App\Filament\Actions\PaymentActions;
use App\Models\Room;
use App\Services\Portal\PortalAccess;
use App\Support\QrCode;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Physical rooms of a type. Rooms are deactivated, never deleted.
 */
class RoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->maxLength(32)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('Short internal code, e.g. R01.'),
                Textarea::make('internal_notes')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->integer()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Bookable')
                    ->default(true),
                Toggle::make('is_demo')
                    ->label('Demo data')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Bookable')
                    ->boolean(),
                IconColumn::make('is_demo')
                    ->label('Demo')
                    ->boolean()
                    ->trueColor('warning'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('portalQr')
                    ->label('Room QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->modalHeading(fn (Room $record): string => "Guest portal QR — {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (Room $record): array => [
                        Text::make(new HtmlString(
                            '<div style="text-align:center"><img src="'.QrCode::pngDataUri($record->portalUrl(), 260).'" alt="QR code for '.e($record->name).'" style="margin:0 auto" width="260" height="260">'
                            .'<p style="margin-top:.5rem;font-size:.8rem;word-break:break-all">'.e($record->portalUrl()).'</p>'
                            .'<p style="margin-top:.5rem;font-size:.8rem">Print and place in the room. The code only identifies the room: guests still confirm with a code sent to their phone.</p></div>',
                        )),
                    ]),
                Action::make('replacePortalQr')
                    ->label('Replace QR')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->authorize(fn (Room $record): bool => auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription('The printed QR in this room stops working and guests signed in through it are signed out. Print the new code afterwards.')
                    ->action(fn (Room $record) => PaymentActions::run(fn () => app(PortalAccess::class)->rotateRoomQr($record, auth()->user()), 'QR code replaced — print the new one')),
            ]);
    }
}
