<?php

declare(strict_types=1);

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Old-site URL redirects (WEB-05).
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Redirects';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')
                ->label('Old address')
                ->placeholder('/old-page.html')
                ->helperText('The path on the old website. A full URL is fine; only the path is kept.')
                ->required()
                ->maxLength(255)
                ->dehydrateStateUsing(fn (string $state): string => Redirect::normalisePath($state))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, ?string $state) => $rule->where('from_path', Redirect::normalisePath((string) $state))),
            TextInput::make('to_path')
                ->label('New address')
                ->placeholder('/rooms')
                ->required()
                ->maxLength(255)
                ->regex('#^(/|https://)#')
                ->helperText('A path on this site (starting with /) or a full https:// address.'),
            Select::make('status_code')
                ->label('Type')
                ->options([301 => 'Permanent (301)', 302 => 'Temporary (302)'])
                ->default(301)
                ->required(),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_path')
            ->columns([
                TextColumn::make('from_path')->label('Old address')->searchable(),
                TextColumn::make('to_path')->label('New address')->searchable(),
                TextColumn::make('status_code')->label('Type')->badge()->color('gray'),
                TextColumn::make('hits')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('Last used')->since()->placeholder('Never'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
