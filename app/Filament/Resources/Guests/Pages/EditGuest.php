<?php

declare(strict_types=1);

namespace App\Filament\Resources\Guests\Pages;

use App\Enums\StaffRole;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Guests\GuestResource;
use App\Models\Guest;
use App\Services\Privacy\GuestPrivacy;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Guest profile, with the POPIA tools: export (access request), legal hold and anonymisation (PRIV-03).
 *
 * @property Guest $record
 */
class EditGuest extends EditRecord
{
    protected static string $resource = GuestResource::class;

    protected function getHeaderActions(): array
    {
        $managesPrivacy = fn (): bool => auth()->user()->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value]);

        return [
            ActionGroup::make([
                Action::make('exportData')
                    ->label('Export their data (access request)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->authorize($managesPrivacy)
                    ->modalDescription('Downloads everything we hold about this guest as a file. The export is recorded in the audit log.')
                    ->requiresConfirmation()
                    ->action(function (): ?StreamedResponse {
                        try {
                            $data = app(GuestPrivacy::class)->export($this->record, auth()->user());
                        } catch (Throwable $exception) {
                            Notification::make()->title('Not exported')->body($exception->getMessage())->danger()->send();

                            return null;
                        }

                        return response()->streamDownload(
                            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                            'guest-data-'.$this->record->id.'.json',
                            ['Content-Type' => 'application/json'],
                        );
                    }),
                Action::make('legalHold')
                    ->label('Place a legal hold')
                    ->icon('heroicon-o-lock-closed')
                    ->visible(fn (): bool => $this->record->legal_hold_at === null && $this->record->anonymised_at === null)
                    ->authorize($managesPrivacy)
                    ->schema([Textarea::make('reason')->required()->rows(2)->placeholder('e.g. Dispute or claim — keep all records')])
                    ->action(fn (array $data) => PaymentActions::run(fn () => app(GuestPrivacy::class)->placeHold($this->record, $data['reason'], auth()->user()), 'Legal hold placed')),
                Action::make('releaseHold')
                    ->label('Release legal hold')
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (): bool => $this->record->legal_hold_at !== null)
                    ->authorize(fn (): bool => auth()->user()->hasRole(StaffRole::Owner->value))
                    ->requiresConfirmation()
                    ->action(fn () => PaymentActions::run(fn () => app(GuestPrivacy::class)->releaseHold($this->record, auth()->user()), 'Legal hold released')),
                Action::make('anonymise')
                    ->label('Anonymise (forget this guest)')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->visible(fn (): bool => $this->record->anonymised_at === null)
                    ->authorize(fn (): bool => auth()->user()->hasRole(StaffRole::Owner->value))
                    ->schema([Textarea::make('reason')->required()->rows(2)->placeholder('e.g. Guest asked to be forgotten (POPIA request of 3 March)')])
                    ->modalDescription('Replaces their name and contact details everywhere they are used for operations. Invoices and receipts keep the printed name (tax records). This cannot be undone.')
                    ->action(function (array $data): void {
                        PaymentActions::run(fn () => app(GuestPrivacy::class)->anonymise($this->record, $data['reason'], auth()->user()), 'Guest anonymised');
                        $this->refreshFormData(['first_name', 'last_name', 'email', 'phone', 'notes']);
                    }),
            ])->label('Privacy')->icon('heroicon-o-shield-check')->button()->color('gray'),
        ];
    }
}
