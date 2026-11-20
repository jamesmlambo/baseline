<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Pages;

use App\Actions\Website\PostWorkflow;
use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * News workflow buttons (CMS-02). Each button calls PostWorkflow, which re-checks permissions.
 */
class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit for review')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (Post $record): bool => $record->status === PostStatus::Draft)
                ->requiresConfirmation()
                ->action(fn (Post $record) => $this->runWorkflow(fn (PostWorkflow $workflow) => $workflow->submitForReview($record, auth()->user()), 'Submitted for review')),
            Action::make('publish')
                ->label(fn (Post $record): string => $record->published_at?->isFuture() ? 'Schedule' : 'Publish')
                ->icon('heroicon-o-globe-alt')
                ->color('success')
                ->visible(fn (Post $record): bool => $record->status !== PostStatus::Published && auth()->user()->can('publish', $record))
                ->requiresConfirmation()
                ->modalDescription('The post appears on the public website at its publish date.')
                ->action(fn (Post $record) => $this->runWorkflow(fn (PostWorkflow $workflow) => $workflow->publish($record, auth()->user()), 'Published')),
            Action::make('back_to_draft')
                ->label('Back to draft')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (Post $record): bool => in_array($record->status, [PostStatus::InReview, PostStatus::Published], true) && auth()->user()->can('publish', $record))
                ->requiresConfirmation()
                ->action(fn (Post $record) => $this->runWorkflow(fn (PostWorkflow $workflow) => $workflow->sendBackToDraft($record, auth()->user()), 'Moved back to draft')),
            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->visible(fn (Post $record): bool => $record->status !== PostStatus::Archived && auth()->user()->can('publish', $record))
                ->requiresConfirmation()
                ->modalDescription('The post is removed from the public website but kept for the record.')
                ->action(fn (Post $record) => $this->runWorkflow(fn (PostWorkflow $workflow) => $workflow->archive($record, auth()->user()), 'Archived')),
            DeleteAction::make(),
        ];
    }

    private function runWorkflow(callable $step, string $message): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        $step(app(PostWorkflow::class));

        $this->refreshFormData(['status', 'published_at']);

        Notification::make()->title($message)->success()->send();
    }
}
