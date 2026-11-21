<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaskChecklistTemplates;

use App\Enums\TaskType;
use App\Filament\Resources\TaskChecklistTemplates\Pages\ManageTaskChecklistTemplates;
use App\Models\TaskChecklistTemplate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The standard checklist copied onto each new task of a type (HK-02, HK-04).
 */
class TaskChecklistTemplateResource extends Resource
{
    protected static ?string $model = TaskChecklistTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Task checklists';

    protected static ?string $modelLabel = 'task checklist';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('task_type')->label('Task type')->options(TaskType::options())->required()->unique(ignoreRecord: true),
            TagsInput::make('items')->label('Checklist items')->required()->reorderable()->placeholder('Add an item and press Enter')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('task_type')->label('Task type')->formatStateUsing(fn (TaskType $state): string => $state->label()),
                TextColumn::make('items')->label('Items')->state(fn (TaskChecklistTemplate $record): string => count($record->items ?? []).' items')
                    ->description(fn (TaskChecklistTemplate $record): string => mb_strimwidth(implode(', ', $record->items ?? []), 0, 120, '…')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTaskChecklistTemplates::route('/'),
        ];
    }
}
