<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\StaffRole;
use App\Filament\Resources\GuestRequests\GuestRequestResource;
use App\Services\Reception\ReceptionOverview;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Sound and pop-up for reception when a guest sends a portal request or message (GST-08).
 * Rendered on every admin page; checks every 15 seconds.
 */
class GuestPortalAlert extends Component
{
    public int $waiting = 0;

    public function mount(ReceptionOverview $overview): void
    {
        $this->waiting = $this->isFrontDesk() ? $overview->guestPortalWaiting() : 0;
    }

    public function check(ReceptionOverview $overview): void
    {
        if (! $this->isFrontDesk()) {
            return;
        }

        $now = $overview->guestPortalWaiting();

        if ($now > $this->waiting) {
            Notification::make()
                ->title('New guest request')
                ->body($now === 1 ? '1 guest is waiting for reception.' : "{$now} guest requests or messages are waiting.")
                ->icon('heroicon-o-bell-alert')
                ->warning()
                ->actions([Action::make('open')->label('Open')->url(GuestRequestResource::getUrl())])
                ->send();

            $this->dispatch('guest-portal-chime');
        }

        $this->waiting = $now;
    }

    public function render(): View
    {
        return view('livewire.guest-portal-alert', ['enabled' => $this->isFrontDesk()]);
    }

    private function isFrontDesk(): bool
    {
        return auth()->user()?->hasAnyRole([
            StaffRole::Administrator->value, StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value,
        ]) ?? false;
    }
}
