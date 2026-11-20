<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn (Post $record): string => $record->type->label()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PostStatus $state): string => $state->label())
                    ->color(fn (PostStatus $state): string => $state->color()),
                TextColumn::make('published_at')
                    ->label('Publish date')
                    ->dateTime('j M Y H:i')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_demo')
                    ->label('Demo')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success'),
                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PostStatus::options()),
                SelectFilter::make('type')->options(PostType::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
