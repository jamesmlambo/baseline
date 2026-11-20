<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Page;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Content')
                    ->columnSpan(2)
                    ->schema([
                        Text::make('This is a built-in page used by the website menus. You can edit everything except deleting it.')
                            ->color('gray')
                            ->visible(fn (?Page $record): bool => (bool) $record?->isSystemPage()),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('summary')
                            ->rows(2)
                            ->maxLength(300)
                            ->helperText('Shown under the page title and on cards linking to this page.'),
                        RichEditor::make('body')
                            ->label('Page content')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('hero')
                            ->label('Header photo')
                            ->collection('hero')
                            ->image()
                            ->imageEditor()
                            ->maxSize(8192)
                            ->helperText('JPEG, PNG or WebP, up to 8 MB. Location data is removed automatically.'),
                    ]),
                Section::make('Settings')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('slug')
                            ->label('Web address')
                            ->prefix('/')
                            ->required()
                            ->alphaDash()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true)
                            ->notIn(['rooms', 'news', 'faqs', 'book', 'check-availability', 'sitemap', 'admin', 'staff', 'stay'])
                            ->helperText('Changing this breaks old links: add a redirect if the page was already public.'),
                        TextInput::make('nav_label')
                            ->label('Menu label')
                            ->maxLength(64),
                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(true),
                        Toggle::make('is_demo')
                            ->label('Demo content')
                            ->helperText('Switch off once the real content is in.'),
                    ]),
                Section::make('Search engines')
                    ->columnSpan(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title')
                            ->maxLength(70)
                            ->helperText('Leave blank to use the page title.'),
                        Textarea::make('meta_description')
                            ->rows(2)
                            ->maxLength(160)
                            ->helperText('Up to 160 characters. Leave blank to use the summary.'),
                    ]),
            ]);
    }
}
