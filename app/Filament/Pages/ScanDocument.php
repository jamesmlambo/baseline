<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Reception\ResolveReceptionCode;
use App\Enums\StaffRole;
use App\Filament\Resources\Bookings\BookingResource;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Reception document scanner (REC-06). Works with the device camera, a USB/Bluetooth scanner
 * (which types the QR content and presses Enter), or a typed code. QR codes printed on invoices
 * open this page directly with ?code=…
 */
class ScanDocument extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?string $navigationLabel = 'Scan document';

    protected static ?string $title = 'Scan a booking document';

    protected static ?string $slug = 'scan';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.scan-document';

    public string $scanned = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            StaffRole::Administrator->value, StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value,
        ]) ?? false;
    }

    public function mount(): void
    {
        $code = request()->query('code');

        if (is_string($code) && $code !== '') {
            $this->lookup($code);
        }
    }

    public function lookup(?string $value = null): void
    {
        $value ??= $this->scanned;
        $booking = app(ResolveReceptionCode::class)->handle($value, auth()->user());

        if ($booking === null) {
            $this->scanned = '';
            Notification::make()
                ->title('No booking found')
                ->body('That code is not recognised. It may be old (revoked) or not from Baseline Lodge.')
                ->danger()
                ->send();

            return;
        }

        $this->redirect(BookingResource::getUrl('view', ['record' => $booking]));
    }
}
