<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Staff\SyncStaffRoles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Staff accounts are deactivated rather than deleted, to keep the audit trail.
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        $data['role_names'] = $record->getRoleNames()->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $roleNames = $data['role_names'] ?? [];
        unset($data['role_names']);

        return DB::transaction(function () use ($record, $data, $roleNames): User {
            $record->update($data);
            app(SyncStaffRoles::class)->handle($record, array_values($roleNames), auth()->user(), 'data.role_names');

            return $record;
        });
    }
}
