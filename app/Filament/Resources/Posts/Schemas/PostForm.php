<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostType;
use App\Models\Post;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Post')
                    ->columnSpan(2)
                    ->schema([
                        Select::make('type')
                            ->options(PostType::options())
                            ->default(PostType::News->value)
                            ->required()
                            ->live(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Web address')
                            ->prefix('/news/')
                            ->required()
                            ->alphaDash()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true),
                        Textarea::make('excerpt')
                            ->label('Short summary')
                            ->rows(2)
                            ->maxLength(300),
                        RichEditor::make('body')
                            ->label('Content')
                            ->required(),
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Cover photo')
                            ->collection('cover')
                            ->disk(config('media-library.disk_name'))
                            ->image()
                            ->imageEditor()
                            ->maxSize(8192),
                    ]),
                Section::make('Status')
                    ->columnSpan(1)
                    ->schema([
                        Text::make(fn (?Post $record): string => 'Status: '.($record?->status->label() ?? 'Draft'))
                            ->weight('bold'),
                        Text::make('Content editors save drafts and submit them for review. A manager or the owner publishes.')
                            ->color('gray'),
                        DateTimePicker::make('published_at')
                            ->label('Publish date')
                            ->seconds(false)
                            ->native(false)
                            ->helperText('Leave blank to publish immediately when approved. A future date schedules the post.')
                            ->visible(fn (): bool => auth()->user()?->can('publish', new Post) ?? false),
                        Toggle::make('is_demo')
                            ->label('Demo content'),
                    ]),
                Section::make('Event details')
                    ->columnSpan(2)
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('type') === PostType::Event->value)
                    ->schema([
                        DateTimePicker::make('event_starts_at')
                            ->label('Starts')
                            ->seconds(false)
                            ->native(false)
                            ->required(fn (Get $get): bool => $get('type') === PostType::Event->value),
                        DateTimePicker::make('event_ends_at')
                            ->label('Ends')
                            ->seconds(false)
                            ->native(false)
                            ->afterOrEqual('event_starts_at'),
                        TextInput::make('event_location')
                            ->label('Where')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Search engines')
                    ->columnSpan(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title')->maxLength(70),
                        Textarea::make('meta_description')->rows(2)->maxLength(160),
                    ]),
            ]);
    }
}
