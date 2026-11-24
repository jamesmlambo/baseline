<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices;

use App\Filament\Actions\PaymentActions;
use App\Models\Invoice;
use App\Services\Finance\DocumentPdf;
use App\Services\Finance\InvoiceDelivery;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Open, download, email and WhatsApp actions, shared by the invoice list and the invoice page.
 */
final class InvoiceActions
{
    public static function open(): Action
    {
        return Action::make('open')
            ->label('Open PDF')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->url(fn (Invoice $record): string => $record->publicUrl(), shouldOpenInNewTab: true);
    }

    /**
     * Saves the invoice PDF to the computer or phone, e.g. to attach it in WhatsApp.
     */
    public static function download(): Action
    {
        return Action::make('download')
            ->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->authorize(fn (Invoice $record): bool => auth()->user()->can('view', $record))
            ->action(function (Invoice $record): StreamedResponse {
                $pdf = app(DocumentPdf::class);
                $contents = $pdf->render($record);

                return response()->streamDownload(fn () => print ($contents), $pdf->filename($record), ['Content-Type' => 'application/pdf']);
            });
    }

    public static function email(): Action
    {
        return Action::make('email')
            ->label('Email')
            ->icon('heroicon-o-envelope')
            ->authorize(fn (Invoice $record): bool => auth()->user()->can('send', $record))
            ->modalHeading(fn (Invoice $record): string => "Email invoice {$record->number}")
            ->modalSubmitActionLabel('Send email')
            ->fillForm(fn (Invoice $record): array => ['email' => $record->booking?->lead_guest_email])
            ->schema([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->helperText('The invoice PDF is attached. It shows PAID or UNPAID as it stands now.'),
                self::messageField(),
            ])
            ->action(fn (Invoice $record, array $data) => PaymentActions::run(
                function () use ($record, $data): void {
                    app(InvoiceDelivery::class)->email($record, $data['email'], auth()->user(), $data['message'] ?? null);
                    $record->unsetRelation('deliveries');
                },
                "Invoice {$record->number} emailed",
            ));
    }

    /**
     * Optional note from staff, shown above the invoice details in the email.
     */
    public static function messageField(): Textarea
    {
        return Textarea::make('message')
            ->label('Message to the guest (optional)')
            ->rows(4)
            ->maxLength(InvoiceDelivery::MAX_MESSAGE_LENGTH)
            ->placeholder('e.g. Thank you for staying with us. The airport transfer has been added as discussed.')
            ->helperText('Shown at the top of the email, above the invoice details.');
    }

    public static function whatsApp(): Action
    {
        return Action::make('whatsapp')
            ->label('WhatsApp')
            ->icon('heroicon-o-chat-bubble-oval-left')
            ->color('success')
            ->authorize(fn (Invoice $record): bool => auth()->user()->can('send', $record))
            ->modalHeading(fn (Invoice $record): string => "WhatsApp invoice {$record->number}")
            ->modalDescription('Opens WhatsApp with a message and the invoice link ready to send.')
            ->modalSubmitActionLabel('Open WhatsApp')
            ->fillForm(fn (Invoice $record): array => ['phone' => $record->booking?->lead_guest_phone])
            ->schema([
                TextInput::make('phone')
                    ->label('WhatsApp number')
                    ->tel()
                    ->maxLength(32)
                    ->helperText('Leave empty to choose the chat in WhatsApp.'),
            ])
            ->action(function (Invoice $record, array $data, Action $action): void {
                $url = app(InvoiceDelivery::class)->whatsAppUrl($record, $data['phone'] ?? null);

                activity('finance')->performedOn($record)->causedBy(auth()->user())->log("Invoice {$record->number} opened in WhatsApp to send");

                $action->getLivewire()->js('window.open('.json_encode($url).', "_blank")');
            });
    }
}
