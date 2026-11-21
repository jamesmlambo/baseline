<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews;

use App\Actions\Portal\ModerateReview;
use App\Enums\ReviewStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Moderation queue for verified-stay reviews (GST-07).
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 40;

    public static function getNavigationBadge(): ?string
    {
        $pending = Review::query()->where('status', ReviewStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('booking')->withCount('media'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Received')->date('j M Y')->sortable(),
                TextColumn::make('rating')->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state)),
                TextColumn::make('display_name')->label('Shown as')->placeholder('—')
                    ->description(fn (Review $record): string => $record->booking->reference),
                TextColumn::make('comment')->wrap()->limit(200)->placeholder('—'),
                TextColumn::make('media_count')->label('Photos'),
                IconColumn::make('consent_to_publish')->label('Consent')->boolean(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ReviewStatus $state): string => $state->label())
                    ->color(fn (ReviewStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                Action::make('publish')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Review $record): bool => $record->status !== ReviewStatus::Approved && $record->consent_to_publish)
                    ->authorize(fn (Review $record): bool => auth()->user()->can('moderate', $record))
                    ->requiresConfirmation()
                    ->modalDescription('The review will appear on the public website.')
                    ->action(fn (Review $record) => PaymentActions::run(fn () => app(ModerateReview::class)->handle($record, ReviewStatus::Approved, auth()->user()), 'Review published')),
                Action::make('reject')
                    ->label(fn (Review $record): string => $record->status === ReviewStatus::Approved ? 'Unpublish' : 'Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (Review $record): bool => $record->status !== ReviewStatus::Rejected)
                    ->authorize(fn (Review $record): bool => auth()->user()->can('moderate', $record))
                    ->requiresConfirmation()
                    ->action(fn (Review $record) => PaymentActions::run(fn () => app(ModerateReview::class)->handle($record, ReviewStatus::Rejected, auth()->user()), 'Review not published')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }
}
