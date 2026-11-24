<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\Pages\Concerns;

use App\Actions\Inventory\SetRoomCount;
use App\Models\AccommodationType;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Saves the room type and applies its "Number of rooms" in one transaction,
 * so a refused room change never leaves the other edits half saved.
 */
trait SavesRoomCount
{
    /**
     * @param  array<string, mixed>  $data
     * @param  Closure(array<string, mixed>): Model  $save
     */
    protected function saveWithRoomCount(array $data, Closure $save): Model
    {
        $count = (int) ($data['room_count'] ?? 0);
        unset($data['room_count']);

        return DB::transaction(function () use ($data, $save, $count): Model {
            /** @var AccommodationType $type */
            $type = $save($data);

            try {
                app(SetRoomCount::class)->handle($type, $count);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['data.room_count' => $exception->getMessage()]);
            }

            return $type;
        });
    }
}
