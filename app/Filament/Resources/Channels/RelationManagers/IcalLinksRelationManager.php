<?php

declare(strict_types=1);

namespace App\Filament\Resources\Channels\RelationManagers;

use App\Models\IcalLink;
use App\Models\Room;
use App\Services\Channels\ChannelSync;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Unique;
use Throwable;

/**
 * CHN-02: the limited iCal fallback, per physical room. Dates only — not a channel manager.
 */
class IcalLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'icalLinks';

    protected static ?string $title = 'iCal calendars (limited fallback)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('room_id')->label('Room')
                ->options(fn (): array => Room::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('channel_id', $this->getOwnerRecord()->getKey())),
            Toggle::make('export_enabled')->label('Let the channel read this room\'s calendar'),
            TextInput::make('import_url')->label('The channel\'s calendar link for this room (optional)')->url()->maxLength(2000)
                ->helperText('Stored for later. Automatic fetching is off until approved; use "Import calendar" meanwhile.'),
        ]);
    }

    public function table(Table $table): Table
    {
        $canUpdate = fn (): bool => auth()->user()->can('update', $this->getOwnerRecord());

        return $table
            ->description('iCal only shares which dates are taken — no prices, guests or room types, and only as often as each side checks. It can still double-book; it is not a channel manager.')
            ->columns([
                TextColumn::make('room.name')->label('Room'),
                IconColumn::make('export_enabled')->label('Shared')->boolean(),
                TextColumn::make('last_imported_at')->label('Last import')->since()->placeholder('Never'),
                TextColumn::make('last_error')->label('Last error')->limit(60)->placeholder('—')->color('danger'),
            ])
            ->headerActions([CreateAction::make()->label('Add room')->authorize($canUpdate)])
            ->recordActions([
                Action::make('exportLink')
                    ->label('Calendar link')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->visible(fn (IcalLink $record): bool => $record->export_enabled)
                    ->authorize($canUpdate)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (IcalLink $record): array => [
                        Text::make('Give this link to the channel as the calendar to import for '.$record->room->name.'. Anyone with the link can see which dates are taken (nothing else).'),
                        TextInput::make('url')->label('Link')->default($record->exportUrl())->readOnly()->copyable(),
                    ]),
                Action::make('import')
                    ->label('Import calendar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->authorize($canUpdate)
                    ->modalDescription('Upload the .ics file from the channel, or paste its contents. Dates taken on the channel block this room here.')
                    ->schema([
                        FileUpload::make('file')->label('.ics file')->disk('local')->directory('ical-imports')->visibility('private')->rules(['extensions:ics,txt'])->maxSize(2048),
                        Textarea::make('content')->label('…or paste the calendar')->rows(6),
                    ])
                    ->action(function (IcalLink $record, array $data): void {
                        $path = is_array($data['file'] ?? null) ? array_values($data['file'])[0] ?? null : ($data['file'] ?? null);
                        $content = filled($path) ? (string) Storage::disk('local')->get($path) : (string) ($data['content'] ?? '');

                        if (filled($path)) {
                            Storage::disk('local')->delete($path);
                        }

                        if (! str_contains($content, 'BEGIN:VCALENDAR')) {
                            Notification::make()->title('Not imported')->body('That is not an iCal calendar.')->danger()->send();

                            return;
                        }

                        try {
                            $counts = app(ChannelSync::class)->importIcal($record, $content);
                        } catch (Throwable $exception) {
                            Notification::make()->title('Not imported')->body($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Calendar imported')
                            ->body("{$counts['added']} date range(s) blocked, {$counts['removed']} released, {$counts['clashes']} clash(es).")
                            ->color($counts['clashes'] > 0 ? 'warning' : 'success')
                            ->send();
                    }),
                Action::make('newLink')
                    ->label('New link')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->visible(fn (IcalLink $record): bool => $record->export_enabled)
                    ->authorize($canUpdate)
                    ->requiresConfirmation()
                    ->modalDescription('The old calendar link stops working. Give the new one to the channel.')
                    ->action(fn (IcalLink $record) => $record->rotateToken()),
                EditAction::make()->authorize($canUpdate),
            ]);
    }
}
