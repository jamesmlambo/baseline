<?php

declare(strict_types=1);

namespace App\Filament\Resources\MenuCategoryRules;

use App\Filament\Resources\MenuCategoryRules\Pages\ManageMenuCategoryRules;
use App\Models\MenuCategoryRule;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * When each menu category can be ordered (LSR-04).
 */
class MenuCategoryRuleResource extends Resource
{
    protected static ?string $model = MenuCategoryRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Leisure';

    protected static ?string $navigationLabel = 'Meal ordering hours';

    protected static ?string $modelLabel = 'ordering window';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('category')->label('Menu category')->required()->maxLength(64)->unique(ignoreRecord: true)
                ->datalist(fn (): array => MenuItem::query()->distinct()->pluck('category')->all())
                ->helperText('Must match the category on the menu items, e.g. Breakfast.'),
            TimePicker::make('orders_open_at')->label('Orders open')->seconds(false)->required(),
            TimePicker::make('orders_close_at')->label('Orders close (cut-off)')->seconds(false)->required()
                ->helperText('If it closes before it opens, the window runs overnight (e.g. 18:00 to 10:00).'),
            TextInput::make('note')->label('Note for guests')->maxLength(255)->placeholder('e.g. Breakfast is served 07:00–10:00.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('category')
            ->columns([
                TextColumn::make('category'),
                TextColumn::make('window')->label('Orders taken')->state(fn (MenuCategoryRule $record): string => $record->windowLabel()),
                TextColumn::make('note')->placeholder('—'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMenuCategoryRules::route('/')];
    }
}
