<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoomBlocks\Pages;

use App\Actions\Inventory\BlockRoom;
use App\Enums\RoomBlockReason;
use App\Exceptions\RoomUnavailableException;
use App\Filament\Resources\RoomBlocks\RoomBlockResource;
use App\Models\Room;
use App\Support\StayDates;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateRoomBlock extends CreateRecord
{
    protected static string $resource = RoomBlockResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(BlockRoom::class)->handle(
                Room::query()->findOrFail($data['room_id']),
                new StayDates($data['starts_on'], $data['ends_on']),
                RoomBlockReason::from($data['reason']),
                $data['notes'] ?? null,
                auth()->user(),
            );
        } catch (RoomUnavailableException $exception) {
            Notification::make()
                ->title('Room not blocked')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
