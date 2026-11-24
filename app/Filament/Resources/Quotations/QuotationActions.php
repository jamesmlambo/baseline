<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations;

use App\Actions\Bookings\BookQuotation;
use App\Enums\BookingSource;
use App\Exceptions\RoomUnavailableException;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Invoices\InvoiceActions;
use App\Models\Quotation;
use App\Services\Finance\DocumentPdf;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\QuotationDelivery;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Open, download, email and WhatsApp actions, shared by the quotation list and the quotation page.
 */
final class QuotationActions
{
    public static function open(): Action
    {
        return Action::make('open')
            ->label('Open PDF')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn (Quotation $record): bool => $record->publicUrl() !== null)
            ->url(fn (Quotation $record): ?string => $record->publicUrl(), shouldOpenInNewTab: true);
    }

    public static function download(): Action
    {
        return Action::make('download')
            ->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->authorize(fn (Quotation $record): bool => auth()->user()->can('view', $record))
            ->action(function (Quotation $record): StreamedResponse {
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
            ->authorize(fn (Quotation $record): bool => auth()->user()->can('send', $record))
            ->modalHeading(fn (Quotation $record): string => "Email quotation {$record->number}")
            ->modalSubmitActionLabel('Send email')
            ->fillForm(fn (Quotation $record): array => ['email' => $record->contactEmail()])
            ->schema([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->helperText('The quotation PDF is attached.'),
                InvoiceActions::messageField(),
            ])
            ->action(fn (Quotation $record, array $data) => PaymentActions::run(
                function () use ($record, $data): void {
                    app(QuotationDelivery::class)->email($record, $data['email'], auth()->user(), $data['message'] ?? null);
                    $record->unsetRelation('deliveries');
                },
                "Quotation {$record->number} emailed",
            ));
    }

    /**
     * Turns the quotation into a booking held for payment, like a front-desk booking.
     */
    public static function book(): Action
    {
        return Action::make('book')
            ->label('Book')
            ->icon('heroicon-o-calendar-days')
            ->color('primary')
            ->authorize(fn (Quotation $record): bool => auth()->user()->can('book', $record))
            ->modalHeading(fn (Quotation $record): string => "Book quotation {$record->number}")
            ->modalDescription(function (Quotation $record): string {
                $text = 'Creates a booking with the quoted rooms, extras and services. Rooms are held until the time below, waiting for payment; spa and day passes go on the invoice for you to give a time in Leisure.';
                $current = app(BookQuotation::class)->priceChange($record);

                return $current === null
                    ? $text
                    : $text." Prices have changed since the quote: the booking will be {$current->format()} instead of the quoted {$record->total_cents->format()}.";
            })
            ->modalSubmitActionLabel('Create booking')
            ->fillForm(function (Quotation $record): array {
                [$first, $last] = array_pad(explode(' ', trim($record->recipient_name), 2), 2, '');

                return [
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $record->contactEmail(),
                    'phone' => $record->contactPhone(),
                    'source' => BookingSource::Phone->value,
                    'hold_until' => now()->addHours(app(FinanceSettings::class)->eftHoldHours() ?? 48)->format('Y-m-d H:i'),
                ];
            })
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('first_name')->required()->maxLength(100),
                    TextInput::make('last_name')->required()->maxLength(100),
                    TextInput::make('email')->email()->maxLength(255),
                    TextInput::make('phone')->tel()->maxLength(32),
                    Select::make('source')
                        ->label('Booked by')
                        ->options(collect([BookingSource::Phone, BookingSource::WalkIn, BookingSource::Email, BookingSource::WhatsApp, BookingSource::Other])
                            ->mapWithKeys(fn (BookingSource $source): array => [$source->value => $source->label()])
                            ->all())
                        ->required(),
                    DateTimePicker::make('hold_until')
                        ->label('Hold the rooms until')
                        ->native(false)
                        ->seconds(false)
                        ->timezone('Africa/Johannesburg')
                        ->minDate(now())
                        ->required(),
                ]),
            ])
            ->action(function (Quotation $record, array $data, Action $action): void {
                try {
                    $booking = app(BookQuotation::class)->handle(
                        $record,
                        ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null],
                        BookingSource::from($data['source']),
                        CarbonImmutable::parse($data['hold_until']),
                        auth()->user(),
                    );
                } catch (InvalidArgumentException|RoomUnavailableException|AuthorizationException $exception) {
                    Notification::make()->title('Booking not created')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()->title("Booking {$booking->reference} created")->success()->send();
                $action->redirect(BookingResource::getUrl('view', ['record' => $booking]));
            });
    }

    public static function whatsApp(): Action
    {
        return Action::make('whatsapp')
            ->label('WhatsApp')
            ->icon('heroicon-o-chat-bubble-oval-left')
            ->color('success')
            ->authorize(fn (Quotation $record): bool => auth()->user()->can('send', $record) && $record->publicUrl() !== null)
            ->modalHeading(fn (Quotation $record): string => "WhatsApp quotation {$record->number}")
            ->modalDescription('Opens WhatsApp with a message and the quotation link ready to send.')
            ->modalSubmitActionLabel('Open WhatsApp')
            ->fillForm(fn (Quotation $record): array => ['phone' => $record->contactPhone()])
            ->schema([
                TextInput::make('phone')
                    ->label('WhatsApp number')
                    ->tel()
                    ->maxLength(32)
                    ->helperText('Leave empty to choose the chat in WhatsApp.'),
            ])
            ->action(function (Quotation $record, array $data, Action $action): void {
                $url = app(QuotationDelivery::class)->whatsAppUrl($record, $data['phone'] ?? null);

                activity('finance')->performedOn($record)->causedBy(auth()->user())->log("Quotation {$record->number} opened in WhatsApp to send");

                $action->getLivewire()->js('window.open('.json_encode($url).', "_blank")');
            });
    }
}
